<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Reports;

use App\Http\Controllers\Controller;
use App\Http\Resources\Farms\Farm\Reports\PlantingProfitLossResource;
use App\Models\Core\Farm;
use App\Models\Core\Planting;
use App\Services\Ledger\Reports\BalanceSheetService;
use App\Services\Ledger\Reports\CashFlowService;
use App\Services\Ledger\Reports\ProfitAndLossService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProfitAndLossService $profitAndLossService,
        protected BalanceSheetService $balanceSheetService,
        protected CashFlowService $cashFlowService,
    ) {}

    /**
     * Income less expenses for a date range, across every farm the user can
     * reach or one of them. Defaults to the year to date — the period a farmer
     * is usually asked about.
     */
    public function profitAndLoss(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'farm_uuid' => ['nullable', 'uuid'],
        ]);

        $farmIds = $this->reportableFarmIds($request, $validated['farm_uuid'] ?? null);

        if ($farmIds === null) {
            return $this->errorResponse(
                'That farm is not available to your account.',
                422,
                ['farm_uuid' => ['Choose a farm you have access to.']]
            );
        }

        $statement = $this->profitAndLossService->generate(
            $farmIds,
            $validated['date_from'] ?? now()->startOfYear()->toDateString(),
            $validated['date_to'] ?? now()->toDateString(),
        );

        return $this->successResponse($statement, 'Profit and loss statement retrieved successfully');
    }

    /**
     * What the farm owns, owes and is worth as at one date. Defaults to today
     * — the figure a bank or SACCO asks for.
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
            'farm_uuid' => ['nullable', 'uuid'],
        ]);

        $farmIds = $this->reportableFarmIds($request, $validated['farm_uuid'] ?? null);

        if ($farmIds === null) {
            return $this->errorResponse(
                'That farm is not available to your account.',
                422,
                ['farm_uuid' => ['Choose a farm you have access to.']]
            );
        }

        $statement = $this->balanceSheetService->generate(
            $farmIds,
            $validated['as_of'] ?? now()->toDateString(),
        );

        return $this->successResponse($statement, 'Balance sheet retrieved successfully');
    }

    /**
     * Where the money actually came from and went over a date range — the
     * answer to "the books say I made a profit, so why is the tin empty?".
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'farm_uuid' => ['nullable', 'uuid'],
        ]);

        $farmIds = $this->reportableFarmIds($request, $validated['farm_uuid'] ?? null);

        if ($farmIds === null) {
            return $this->errorResponse(
                'That farm is not available to your account.',
                422,
                ['farm_uuid' => ['Choose a farm you have access to.']]
            );
        }

        $statement = $this->cashFlowService->generate(
            $farmIds,
            $validated['date_from'] ?? now()->startOfYear()->toDateString(),
            $validated['date_to'] ?? now()->toDateString(),
        );

        return $this->successResponse($statement, 'Cash flow statement retrieved successfully');
    }

    /**
     * Farms whose money this user may total. `farmerOwned` already applies
     * both layers — farmer membership and any farm pinning — so reports
     * inherit the same boundary as every other listing.
     *
     * @return Collection<int, int>|null Null when the requested farm is out of reach.
     */
    private function reportableFarmIds(Request $request, ?string $farmUuid): ?Collection
    {
        $farms = Farm::query()->farmerOwned($request->user()->id);

        if ($farmUuid === null) {
            return $farms->pluck('id');
        }

        $farmIds = $farms->where('uuid', $farmUuid)->pluck('id');

        return $farmIds->isEmpty() ? null : $farmIds;
    }

    public function profitAndLossByPlantings(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        $plantings = Planting::query()
            ->with(['farm', 'crop', 'field'])
            ->whereHas('farm', function ($query) use ($farmerIds) {
                $query->whereIn('farmer_id', $farmerIds);
            })
            ->withSum([
                'ledgerTransactions as revenue_total' => function ($query) {
                    $query->join('ledger_entries', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                        ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')
                        ->where('ledger_accounts.type', 'revenue');
                },
            ], 'ledger_entries.amount')
            ->withSum([
                'ledgerTransactions as expense_total' => function ($query) {
                    $query->join('ledger_entries', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')
                        ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')
                        ->where('ledger_accounts.type', 'expense');
                },
            ], 'ledger_entries.amount')
            ->orderByDesc('date_planted')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            PlantingProfitLossResource::collection($plantings),
            'Profit and loss by planting retrieved successfully'
        );
    }
}
