<?php

namespace App\Services\Sales;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Buyer;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\Core\Production;
use App\Models\Core\Sale;
use App\Models\Core\SaleItem;
use App\Models\Core\SalePayment;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The farmer-facing "I sold something" flow. Owns the Sale/SaleItem records
 * and their herd side effects; all money movements are delegated to
 * LedgerTransactionService so the books stay double-entry correct.
 */
class SaleService
{
    public function __construct(
        protected DatabaseManager $db,
        protected LedgerTransactionService $ledger,
    ) {}

    public function store(User $user, Farm $farm, array $data, string $uuid): Sale
    {
        $items = $this->normalizeItems($data['items']);
        $amountTotal = round(array_sum(array_column($items, 'line_total')), 2);
        $isCredit = $data['payment_method'] === 'credit';

        return $this->db->transaction(function () use ($user, $farm, $data, $uuid, $items, $amountTotal, $isCredit) {
            $buyer = $this->resolveBuyer($user, $farm->farmer_id, $data);

            $sale = Sale::create([
                'uuid' => $uuid,
                'farm_id' => $farm->id,
                'farmer_id' => $farm->farmer_id,
                'user_id' => $user->id,
                'buyer_id' => $buyer?->id,
                'date' => Carbon::parse($data['date'])->toDateString(),
                'payment_method' => $data['payment_method'],
                'amount_total' => $amountTotal,
                'amount_paid' => $isCredit ? 0 : $amountTotal,
                'status' => $isCredit ? Sale::STATUS_OWED : Sale::STATUS_PAID,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $sellable = $this->resolveSellable($farm, $item);

                SaleItem::create([
                    'uuid' => $item['uuid'] ?? (string) Str::orderedUuid(),
                    'sale_id' => $sale->id,
                    'sellable_type' => $sellable ? $item['sellable_type'] : null,
                    'sellable_id' => $sellable?->getKey(),
                    'category' => $item['category'],
                    'product' => $item['product'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'production_id' => $this->resolveProductionId($item),
                ]);

                $this->applySellSideEffects($sellable, $item);
            }

            $transaction = $this->postIncome($user, $sale, $items);
            $sale->update(['ledger_transaction_id' => $transaction->id]);

            return $sale->load(['buyer', 'items', 'payments']);
        });
    }

    public function recordPayment(User $user, Sale $sale, array $data): SalePayment
    {
        if ($sale->status === Sale::STATUS_VOID) {
            throw ValidationException::withMessages([
                'sale' => ['This sale has been voided and cannot receive payments.'],
            ]);
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount > $sale->balanceDue()) {
            throw ValidationException::withMessages([
                'amount' => ['The payment is larger than what is still owed ('.number_format($sale->balanceDue(), 2).').'],
            ]);
        }

        return $this->db->transaction(function () use ($user, $sale, $data, $amount) {
            $transaction = $this->ledger->transfer(
                user: $user,
                farmerId: $sale->farmer_id,
                farmId: $sale->farm_id,
                transactionable: $sale,
                date: Carbon::parse($data['date']),
                amount: $amount,
                fromAccount: $this->findAccount('Accounts Receivable', $sale->farmer_id),
                toAccount: $this->paymentAccount($data['payment_method'], $sale->farmer_id),
                description: 'Payment received for sale '.$sale->uuid,
                paymentMethod: $data['payment_method'],
            );

            $payment = SalePayment::create([
                'uuid' => $data['uuid'] ?? (string) Str::orderedUuid(),
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'date' => Carbon::parse($data['date'])->toDateString(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'ledger_transaction_id' => $transaction->id,
            ]);

            $amountPaid = round($sale->amount_paid + $amount, 2);
            $sale->update([
                'amount_paid' => $amountPaid,
                'status' => $amountPaid >= $sale->amount_total ? Sale::STATUS_PAID : Sale::STATUS_PARTIAL,
            ]);

            return $payment;
        });
    }

    public function void(User $user, Sale $sale): Sale
    {
        if ($sale->status === Sale::STATUS_VOID) {
            throw ValidationException::withMessages([
                'sale' => ['This sale is already voided.'],
            ]);
        }

        if ($sale->payments()->exists()) {
            throw ValidationException::withMessages([
                'sale' => ['This sale has payments recorded against it. Remove them first before voiding.'],
            ]);
        }

        return $this->db->transaction(function () use ($user, $sale) {
            if ($sale->ledgerTransaction) {
                $this->ledger->reverse($user, $sale->ledgerTransaction, 'Void of sale '.$sale->uuid);
            }

            foreach ($sale->items()->with('sellable')->get() as $item) {
                $this->revertSellSideEffects($item->sellable, $item);
            }

            $sale->update(['status' => Sale::STATUS_VOID]);

            return $sale->load(['buyer', 'items', 'payments']);
        });
    }

    /** Fills in whichever of unit_price / line_total the farmer left blank. */
    protected function normalizeItems(array $items): array
    {
        return array_map(function (array $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                ? (float) $item['unit_price'] : null;
            $lineTotal = isset($item['line_total']) && $item['line_total'] !== null && $item['line_total'] !== ''
                ? (float) $item['line_total'] : null;

            if ($lineTotal === null && $unitPrice === null) {
                throw ValidationException::withMessages([
                    'items' => ['Each item needs a price per unit or a total amount.'],
                ]);
            }

            $lineTotal ??= round($quantity * $unitPrice, 2);
            if ($unitPrice === null && $quantity > 0) {
                $unitPrice = round($lineTotal / $quantity, 2);
            }

            return [...$item, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => $lineTotal];
        }, $items);
    }

    protected function resolveBuyer(User $user, int $farmerId, array $data): ?Buyer
    {
        if (! empty($data['buyer_uuid'])) {
            return Buyer::where('uuid', $data['buyer_uuid'])
                ->where('farmer_id', $farmerId)
                ->firstOrFail();
        }

        if (! empty($data['buyer']['name'])) {
            return Buyer::firstOrCreate(
                ['farmer_id' => $farmerId, 'name' => $data['buyer']['name'], 'phone' => $data['buyer']['phone'] ?? null],
                ['uuid' => (string) Str::orderedUuid(), 'user_id' => $user->id]
            );
        }

        return null;
    }

    protected function resolveSellable(Farm $farm, array $item): ?Model
    {
        if (empty($item['sellable_type']) || empty($item['sellable_uuid'])) {
            return null;
        }

        $sellable = match ($item['sellable_type']) {
            'animal' => Animal::where('uuid', $item['sellable_uuid'])->firstOrFail(),
            'animal_group' => AnimalGroup::where('uuid', $item['sellable_uuid'])->firstOrFail(),
            'planting' => Planting::where('uuid', $item['sellable_uuid'])->firstOrFail(),
            'hive' => Hive::where('uuid', $item['sellable_uuid'])->firstOrFail(),
            default => throw ValidationException::withMessages([
                'items' => ["Unsupported sale target [{$item['sellable_type']}]."],
            ]),
        };

        if ((int) $sellable->farm_id !== $farm->id) {
            throw ValidationException::withMessages([
                'items' => ['The selected sale target does not belong to this farm.'],
            ]);
        }

        return $sellable;
    }

    protected function resolveProductionId(array $item): ?int
    {
        if (empty($item['production_uuid'])) {
            return null;
        }

        return Production::where('uuid', $item['production_uuid'])->value('id');
    }

    /** Selling an animal marks it sold; selling N head shrinks the group. */
    protected function applySellSideEffects(?Model $sellable, array $item): void
    {
        if ($sellable instanceof Animal && $item['category'] === 'animal') {
            $sellable->status = 'sold';
            $sellable->saveQuietly();
        }

        if ($sellable instanceof AnimalGroup && $item['category'] === 'animal') {
            $sellable->current_count = max(0, (int) $sellable->current_count - (int) $item['quantity']);
            $sellable->saveQuietly();
        }
    }

    protected function revertSellSideEffects(?Model $sellable, SaleItem $item): void
    {
        if ($sellable instanceof Animal && $item->category === 'animal') {
            $sellable->status = 'active';
            $sellable->saveQuietly();
        }

        if ($sellable instanceof AnimalGroup && $item->category === 'animal') {
            $sellable->current_count = (int) $sellable->current_count + (int) $item->quantity;
            $sellable->saveQuietly();
        }
    }

    /**
     * One income posting per sale. The account comes from the biggest item's
     * category so the farmer never has to pick one (see Sale::CATEGORY_INCOME_ACCOUNTS).
     */
    protected function postIncome(User $user, Sale $sale, array $items)
    {
        usort($items, fn ($a, $b) => $b['line_total'] <=> $a['line_total']);
        $primary = $items[0];

        $accountName = Sale::CATEGORY_INCOME_ACCOUNTS[$primary['category']] ?? 'Other Income';
        $account = $this->findAccount($accountName, $sale->farmer_id);

        $dto = new LedgerTransactionDTO(
            farmerId: $sale->farmer_id,
            farmId: $sale->farm_id,
            date: Carbon::parse($sale->date),
            paymentMethod: $sale->payment_method,
            transactionType: 'revenue',
            ledgerAccountId: $account->id,
            amount: $sale->amount_total,
            description: 'Sale of '.collect($items)->pluck('product')->unique()->implode(', '),
            referenceNumber: null,
            transactionFor: 'sale',
            transactionUuid: $sale->uuid,
            quantity: count($items) === 1 ? (int) $primary['quantity'] : null,
            unitCost: count($items) === 1 ? $primary['unit_price'] : null,
        );

        return $this->ledger->store($user, $dto);
    }

    protected function paymentAccount(string $paymentMethod, int $farmerId): LedgerAccount
    {
        $name = match ($paymentMethod) {
            'cash' => 'Cash',
            'mobile_money' => 'Mobile Money',
            'bank' => 'Bank',
            default => throw ValidationException::withMessages([
                'payment_method' => ['Unsupported payment method for a payment.'],
            ]),
        };

        return $this->findAccount($name, $farmerId);
    }

    protected function findAccount(string $name, int $farmerId): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('name', $name)
            ->where(function ($query) use ($farmerId) {
                $query->whereNull('farmer_id')->orWhere('farmer_id', $farmerId);
            })
            ->orderByDesc('farmer_id')
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'ledger_account' => ["The ledger account [{$name}] is missing. Run the ledger accounts seeder."],
            ]);
        }

        return $account;
    }
}
