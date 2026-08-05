<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\DTOs\LedgerTransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreSalaryRequest;
use App\Http\Resources\Farms\Farm\LedgerTransactionResource;
use App\Models\Core\Farm;
use App\Models\Core\FarmPersonnel;
use App\Models\Core\LedgerAccount;
use App\Models\Core\LedgerTransaction;
use App\Services\Ledger\LedgerTransactionService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Salaries and wages are just a whole-farm expense: they aren't tied to one
 * animal or planting, so they post against the farm itself, to the Labour
 * account. One payment = one expense.
 */
class SalariesController extends Controller
{
    use ApiResponse;

    public function __construct(protected LedgerTransactionService $ledgerTransactionService) {}

    /** List salary payments (farm Labour expenses) for a farm. */
    public function index(string $farmUuid): JsonResponse
    {
        $farm = Farm::farmerOwned(request()->user()->id)->where('uuid', $farmUuid)->first();

        if (! $farm) {
            return $this->errorResponse('Farm not found', 404);
        }

        $labourIds = LedgerAccount::query()->where('name', 'Labour')->pluck('id');

        $transactions = LedgerTransaction::query()
            ->with(['transactionable', 'entries' => fn ($q) => $q->with('account')->orderBy('id')->limit(1)])
            ->where('transactionable_type', Farm::class)
            ->where('transactionable_id', $farm->id)
            ->whereHas('entries', fn ($q) => $q->whereIn('ledger_account_id', $labourIds))
            ->latest('date')
            ->latest('id')
            ->get();

        return $this->successResponse(
            LedgerTransactionResource::collection($transactions),
            'Salaries retrieved successfully'
        );
    }

    public function store(StoreSalaryRequest $request): JsonResponse
    {
        $farm = Farm::farmerOwned($request->user()->id)
            ->where('uuid', $request->validated('farm_uuid'))
            ->first();

        if (! $farm) {
            return $this->errorResponse('Farm not found', 404);
        }

        // Idempotent replay from the offline queue.
        if ($request->filled('uuid')) {
            $existing = LedgerTransaction::where('uuid', $request->validated('uuid'))->first();
            if ($existing) {
                return $this->successResponse(
                    new LedgerTransactionResource($existing->load('transactionable', 'entries.account')),
                    'Salary already recorded'
                );
            }
        }

        $account = LedgerAccount::query()
            ->where('type', 'expense')
            ->where('name', 'Labour')
            ->where(fn ($q) => $q->whereNull('farmer_id')->orWhere('farmer_id', $farm->farmer_id))
            ->orderByDesc('farmer_id')
            ->first();

        if (! $account) {
            return $this->errorResponse('No Labour expense account was found to record the salary.', 422, [
                'amount' => ['No Labour expense account was found to record the salary.'],
            ]);
        }

        $worker = $this->resolveWorkerName($request, $farm);
        $period = $request->validated('period');
        $description = 'Salary'
            .($worker ? " — {$worker}" : '')
            .($period ? " ({$period})" : '');

        try {
            $transaction = DB::transaction(fn () => $this->ledgerTransactionService->store(
                $request->user(),
                new LedgerTransactionDTO(
                    farmerId: (int) $farm->farmer_id,
                    farmId: (int) $farm->id,
                    date: Carbon::parse($request->validated('date')),
                    paymentMethod: $request->validated('payment_method'),
                    transactionType: 'expense',
                    ledgerAccountId: $account->id,
                    amount: (float) $request->validated('amount'),
                    description: $request->validated('notes') ? $description.' — '.$request->validated('notes') : $description,
                    referenceNumber: null,
                    transactionFor: 'farm',
                    transactionUuid: $farm->uuid,
                    quantity: null,
                    unitCost: null,
                    uuid: $request->validated('uuid'),
                    scope: 'general',
                )
            ));

            return $this->successResponse(
                new LedgerTransactionResource($transaction->load('transactionable', 'entries.account')),
                'Salary recorded successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to record the salary', 500, ['exception' => $e->getMessage()]);
        }
    }

    protected function resolveWorkerName(StoreSalaryRequest $request, Farm $farm): ?string
    {
        if ($request->filled('farm_personnel_uuid')) {
            $person = FarmPersonnel::query()
                ->where('uuid', $request->validated('farm_personnel_uuid'))
                ->where('farmer_id', $farm->farmer_id)
                ->first();

            if ($person) {
                return $person->role ? "{$person->name} ({$person->role})" : $person->name;
            }
        }

        return $request->validated('worker_name') ?: null;
    }
}
