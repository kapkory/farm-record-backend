<?php

namespace App\Services\Task;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\Core\Task;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TaskExpenseRecorder
{
    public function __construct(protected LedgerTransactionService $ledgerTransactionService)
    {
    }

    public function recordForTask(User $user, Task $task, array $validated): void
    {
        $expenseAmount = (float) ($validated['expense_amount'] ?? 0);

        if (! ($validated['record_expense'] ?? false) || $expenseAmount <= 0) {
            return;
        }

        $taskable = $task->taskable;

        if (! $taskable) {
            throw ValidationException::withMessages([
                'expense_amount' => ['This task is not linked to a farm record, so an expense cannot be recorded.'],
            ]);
        }

        [$farm, $transactionFor, $transactionUuid] = $this->resolveFarmContext($taskable);

        // Names must match the seeded chart of accounts (LedgerAccountsSeeder)
        // exactly — 'Labor'/'Veterinary' never existed, so the task expense
        // only ever resolved to 'Fertilizer & Chemicals' by luck.
        $expenseAccount = LedgerAccount::query()
            ->where('type', 'expense')
            ->whereIn('name', ['Labour', 'Fertilizer & Chemicals', 'Veterinary & Medicine'])
            ->where(function ($query) use ($farm) {
                $query->whereNull('farmer_id')
                    ->orWhere('farmer_id', $farm->farmer_id);
            })
            ->orderByRaw("CASE name
                WHEN 'Labour' THEN 1
                WHEN 'Fertilizer & Chemicals' THEN 2
                WHEN 'Veterinary & Medicine' THEN 3
                ELSE 99 END")
            ->orderByDesc('farmer_id')
            ->first();

        if (! $expenseAccount) {
            throw ValidationException::withMessages([
                'expense_amount' => ['No default task expense ledger account was found.'],
            ]);
        }

        $dto = new LedgerTransactionDTO(
            farmerId: (int) $farm->farmer_id,
            farmId: (int) $farm->id,
            date: Carbon::parse($validated['due_date'] ?? now()),
            paymentMethod: 'cash',
            transactionType: 'expense',
            ledgerAccountId: $expenseAccount->id,
            amount: $expenseAmount,
            description: 'Task expense for '.$task->title,
            referenceNumber: null,
            transactionFor: $transactionFor,
            transactionUuid: $transactionUuid,
            quantity: null,
            unitCost: null,
        );

        $this->ledgerTransactionService->store($user, $dto);
    }

    /**
     * The ledger only accepts postings against the transactionable types
     * TransactionableResolver knows about (planting/animal_group/animal/
     * hive/sale) — a task on a bare Farm or a Treatment has no matching
     * ledger target, so those fall through to the validation error below.
     *
     * @return array{0: Farm, 1: string, 2: string}
     */
    protected function resolveFarmContext(Model $taskable): array
    {
        return match (true) {
            $taskable instanceof Planting => [$taskable->farm, 'planting', $taskable->uuid],
            $taskable instanceof AnimalGroup => [$taskable->farm, 'animal_group', $taskable->uuid],
            $taskable instanceof Animal => [$taskable->farm, 'animal', $taskable->uuid],
            $taskable instanceof Hive => [$taskable->farm, 'hive', $taskable->uuid],
            default => throw ValidationException::withMessages([
                'expense_amount' => ['Expenses cannot be recorded for this type of task yet.'],
            ]),
        };
    }
}
