<?php

namespace App\Services\Inputs;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\FarmInput;
use App\Models\Core\LedgerAccount;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Posts the cost of a bulk input to the ledger — once, when it was bought.
 *
 * This is the only place an input touches the books. Applications allocate this
 * already-posted amount; they never post again, or a single tin of dip would be
 * counted once at purchase and again at every dipping.
 *
 * Follows AnimalPurchaseRecorder, including its rule that a missing ledger
 * account is logged and skipped rather than thrown: losing the purchase record
 * because the chart of accounts is incomplete would be a worse outcome.
 */
class InputPurchaseRecorder
{
    /** Best expense account per category, most specific first. */
    protected const ACCOUNTS_BY_CATEGORY = [
        'dip' => ['Veterinary & Medicine', 'General Expenses'],
        'drug' => ['Veterinary & Medicine', 'General Expenses'],
        'vaccine' => ['Veterinary & Medicine', 'General Expenses'],
        'feed' => ['Animal Feed', 'General Expenses'],
        'fertilizer' => ['Fertilizer & Chemicals', 'General Expenses'],
        'seed' => ['Seeds & Seedlings', 'General Expenses'],
        'other' => ['General Expenses'],
    ];

    public function __construct(protected LedgerTransactionService $ledgerTransactionService) {}

    public function record(User $user, FarmInput $input): void
    {
        if ((float) $input->total_cost <= 0 || $input->ledger_transaction_id) {
            return;
        }

        $farm = $input->relationLoaded('farm') ? $input->farm : $input->farm()->first();

        if (! $farm) {
            return;
        }

        $account = $this->resolveAccount($input, (int) $farm->farmer_id);

        if (! $account) {
            Log::warning('No expense account found for a farm input; purchase not posted.', [
                'farm_input_uuid' => $input->uuid,
                'category' => $input->category,
            ]);

            return;
        }

        $dto = new LedgerTransactionDTO(
            farmerId: (int) $farm->farmer_id,
            farmId: (int) $farm->id,
            date: Carbon::parse($input->purchase_date ?? now()),
            paymentMethod: 'cash',
            transactionType: 'expense',
            ledgerAccountId: $account->id,
            amount: (float) $input->total_cost,
            description: sprintf(
                'Purchase of %s (%s %s)',
                $input->name,
                rtrim(rtrim(number_format((float) $input->quantity, 3, '.', ''), '0'), '.'),
                $input->unit
            ),
            referenceNumber: $input->supplier,
            transactionFor: 'farm_input',
            transactionUuid: $input->uuid,
            quantity: (int) round((float) $input->quantity),
            unitCost: (float) $input->unit_cost,
        );

        try {
            $transaction = $this->ledgerTransactionService->store($user, $dto);
            $input->ledger_transaction_id = $transaction->id;
            $input->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('Failed to post a farm input purchase to the ledger.', [
                'farm_input_uuid' => $input->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveAccount(FarmInput $input, int $farmerId): ?LedgerAccount
    {
        $preferred = self::ACCOUNTS_BY_CATEGORY[$input->category] ?? ['General Expenses'];

        // Bound placeholders rather than interpolated names — the ordering is
        // driven by data, so it goes through the query builder's bindings.
        $whens = implode(' ', array_map(
            fn (int $i) => 'WHEN ? THEN '.($i + 1),
            array_keys($preferred)
        ));

        return LedgerAccount::query()
            ->where('type', 'expense')
            ->whereIn('name', $preferred)
            ->where(fn ($q) => $q->whereNull('farmer_id')->orWhere('farmer_id', $farmerId))
            ->orderByRaw("CASE name {$whens} ELSE 99 END", $preferred)
            ->orderByDesc('farmer_id')
            ->first();
    }
}
