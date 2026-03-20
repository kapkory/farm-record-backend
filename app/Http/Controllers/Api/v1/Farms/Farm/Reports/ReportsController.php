<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Reports;

use App\Http\Controllers\Controller;
use App\Http\Resources\Farms\Farm\Reports\PlantingProfitLossResource;
use App\Models\Core\Planting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    use ApiResponse;

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
