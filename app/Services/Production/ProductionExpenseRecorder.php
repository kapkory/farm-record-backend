<?php

namespace App\Services\Production;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ProductionExpenseRecorder
{
    public function __construct(protected LedgerTransactionService $ledgerTransactionService)
    {
    }

    public function recordForPlanting(User $user, Planting $planting, array $validated): void
    {
        $expenseAmount = (float) ($validated['expense_amount'] ?? 0);

        if (! ($validated['record_expense'] ?? false) || $expenseAmount <= 0) {
            return;
        }

        $expenseAccount = LedgerAccount::query()
            ->where('type', 'expense')
            ->where('name', 'Harvest Expenses')
            ->where(function ($query) use ($planting) {
                $query->whereNull('farmer_id')
                    ->orWhere('farmer_id', $planting->farm->farmer_id);
            })
            ->orderByDesc('farmer_id')
            ->first();

        if (! $expenseAccount) {
            throw ValidationException::withMessages([
                'expense_amount' => ['No default production expense ledger account was found.'],
            ]);
        }

        $dto = new LedgerTransactionDTO(
            farmerId: (int) $planting->farm->farmer_id,
            farmId: (int) $planting->farm_id,
            date: Carbon::parse($validated['date']),
            paymentMethod: 'cash',
            transactionType: 'expense',
            ledgerAccountId: $expenseAccount->id,
            amount: $expenseAmount,
            description: 'Production expense for '.$validated['name'],
            referenceNumber: $validated['trace_number'] ?? null,
            transactionFor: 'planting',
            transactionUuid: $planting->uuid,
            quantity: null,
            unitCost: null,
        );

        $this->ledgerTransactionService->store($user, $dto);
    }
}

