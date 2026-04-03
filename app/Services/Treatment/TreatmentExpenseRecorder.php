<?php

namespace App\Services\Treatment;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\AnimalGroup;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\Core\TreatmentType;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TreatmentExpenseRecorder
{
    public function __construct(protected LedgerTransactionService $ledgerTransactionService)
    {
    }

    public function recordForPlanting(User $user, Planting $planting, array $validated): void
    {
        $this->recordForTarget($user, $planting->load('farm'), $validated, 'planting');
    }

    public function recordForAnimalGroup(User $user, AnimalGroup $animalGroup, array $validated): void
    {
        $this->recordForTarget($user, $animalGroup->load('farm'), $validated, 'animal_group');
    }

    protected function recordForTarget(User $user, Model $target, array $validated, string $transactionFor): void
    {
        $expenseAmount = (float) ($validated['expense_amount'] ?? 0);

        if (! ($validated['record_expense'] ?? false) || $expenseAmount <= 0) {
            return;
        }

        $treatmentType = TreatmentType::query()->find($validated['treatment_type_id']);
        $preferredAccounts = ['Fertilizer & Chemicals', 'Veterinary', 'Labor'];

        if ($treatmentType?->type === 'livestock') {
            $preferredAccounts = ['Veterinary', 'Labor'];
        }

        $expenseAccount = LedgerAccount::query()
            ->where('type', 'expense')
            ->whereIn('name', $preferredAccounts)
            ->where(function ($query) use ($target) {
                $query->whereNull('farmer_id')
                    ->orWhere('farmer_id', $target->farm->farmer_id);
            })
            ->orderByRaw("CASE name
                WHEN 'Fertilizer & Chemicals' THEN 1
                WHEN 'Veterinary' THEN 2
                WHEN 'Labor' THEN 3
                ELSE 99 END")
            ->orderByDesc('farmer_id')
            ->first();

        if (! $expenseAccount) {
            throw ValidationException::withMessages([
                'expense_amount' => ['No default treatment expense ledger account was found.'],
            ]);
        }

        $dto = new LedgerTransactionDTO(
            farmerId: (int) $target->farm->farmer_id,
            farmId: (int) $target->farm_id,
            date: Carbon::parse($validated['date']),
            paymentMethod: 'cash',
            transactionType: 'expense',
            ledgerAccountId: $expenseAccount->id,
            amount: $expenseAmount,
            description: 'Treatment expense for '.$validated['details'],
            referenceNumber: null,
            transactionFor: $transactionFor,
            transactionUuid: $target->uuid,
            quantity: null,
            unitCost: null,
        );

        $this->ledgerTransactionService->store($user, $dto);
    }
}

