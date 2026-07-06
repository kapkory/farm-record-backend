<?php

namespace App\Services\Ledger;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\FarmerUser;
use App\Models\Core\LedgerAccount;
use App\Models\Core\LedgerEntry;
use App\Models\Core\LedgerTransaction;
use App\Models\User;
use App\Services\Ledger\Resolvers\TransactionableResolver;
use App\Services\Ledger\Support\LedgerPostingRuleResolver;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LedgerTransactionService
{
    public function __construct(
        protected DatabaseManager $db,
        protected TransactionableResolver $transactionableResolver,
        protected LedgerPostingRuleResolver $postingRuleResolver,
    ) {}

    public function store(User $user, LedgerTransactionDTO $dto): LedgerTransaction
    {
        $transactionable = $this->transactionableResolver->resolve($dto->transactionFor, $dto->transactionUuid);
        $this->guardOwnership($user, $dto->farmerId, $dto->farmId, $transactionable);

        $primaryAccount = LedgerAccount::query()->findOrFail($dto->ledgerAccountId);
        $this->guardPrimaryAccount($dto->transactionType, $primaryAccount->type);

        $contraAccount = $this->resolveContraAccount($dto->farmerId, $dto->paymentMethod, $dto->transactionType);

        return $this->db->transaction(function () use ($dto, $transactionable, $primaryAccount, $contraAccount, $user) {
            $transaction = LedgerTransaction::create([
                'uuid' => $dto->uuid ?? (string) Str::orderedUuid(),
                'farm_id' => $dto->farmId,
                'date' => $dto->date->toDateString(),
                'description' => $dto->description,
                'payment_method' => $dto->paymentMethod,
                'reference_number' => $dto->referenceNumber,
                'transactionable_type' => $transactionable::class,
                'transactionable_id' => $transactionable->getKey(),
                'farmer_id' => $dto->farmerId,
            ]);

            $primaryEffect = $this->postingRuleResolver->effectForPrimaryAccount($dto->transactionType);
            $contraEffect = $this->postingRuleResolver->contraEffect($dto->transactionType);

            $entries = [
                [
                    'uuid' => (string) Str::orderedUuid(),
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $primaryAccount->id,
                    'type' => $this->postingRuleResolver->entryTypeFor($primaryAccount->type, $primaryEffect),
                    'amount' => $dto->amount,
                    'quantity' => $dto->quantity,
                    'unit_price' => $dto->unitCost,
                    'user_id' => $user->id,
                ],
                [
                    'uuid' => (string) Str::orderedUuid(),
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $contraAccount->id,
                    'type' => $this->postingRuleResolver->entryTypeFor($contraAccount->type, $contraEffect),
                    'amount' => $dto->amount,
                    'quantity' => null,
                    'unit_price' => null,
                    'user_id' => $user->id,
                ],
            ];

            foreach ($entries as $entry) {
                LedgerEntry::create($entry);
            }

            return $transaction->load('entries.account', 'transactionable');
        });
    }

    protected function guardOwnership(User $user, int $farmerId, int $farmId, object $transactionable): void
    {
        $belongsToFarmer = FarmerUser::query()
            ->where('user_id', $user->id)
            ->where('farmer_id', $farmerId)
            ->exists();

        if (! $belongsToFarmer) {
            throw ValidationException::withMessages([
                'farmer_id' => ['You are not allowed to post transactions for this farmer.'],
            ]);
        }

        if ((int) $transactionable->farm_id !== $farmId) {
            throw ValidationException::withMessages([
                'transaction_uuid' => ['The selected transaction target does not belong to the resolved farm.'],
            ]);
        }
    }

    protected function guardPrimaryAccount(string $transactionType, string $accountType): void
    {
        $expected = match ($transactionType) {
            'expense' => 'expense',
            'revenue' => 'revenue',
            'asset' => 'asset',
            'liability' => 'liability',
            'equity' => 'equity',
            default => throw ValidationException::withMessages([
                'type' => ['Unsupported transaction type.'],
            ]),
        };

        if ($accountType !== $expected) {
            throw ValidationException::withMessages([
                'entries.0.ledger_account_id' => ["The selected ledger account must be of type [{$expected}]."],
            ]);
        }
    }

    protected function resolveContraAccount(int $farmerId, string $paymentMethod, string $transactionType): LedgerAccount
    {
        $contraType = $this->postingRuleResolver->contraAccountTypeFor($paymentMethod, $transactionType);
        $name = match ($paymentMethod) {
            'cash' => 'Cash',
            'mobile_money' => 'Mobile Money',
            'bank' => 'Bank',
            'credit' => $transactionType === 'income' || $transactionType === 'revenue' ? 'Accounts Receivable' : 'Accounts Payable',
            default => null,
        };

        $query = LedgerAccount::query()
            ->where('type', $contraType)
            ->where(function ($query) use ($farmerId) {
                $query->whereNull('farmer_id')
                    ->orWhere('farmer_id', $farmerId);
            });

        if ($name !== null) {
            $query->where('name', $name);
        }

        $account = $query->orderByDesc('farmer_id')->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'payment_method' => ['No contra ledger account was found for the selected payment method.'],
            ]);
        }

        return $account;
    }
}
