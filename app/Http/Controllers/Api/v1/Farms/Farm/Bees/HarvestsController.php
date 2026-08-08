<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Bees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bees\StoreHarvestRequest;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\Production;
use App\Models\Core\Sale;
use App\Services\Bees\HiveHarvestService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HarvestsController extends Controller
{
    use ApiResponse;

    public function __construct(protected HiveHarvestService $harvestService) {}


    public function store(StoreHarvestRequest $request): JsonResponse
    {
        try {
            $result = $this->harvestService->record($request->user(), $request->validated());

            return $result['replayed']
                ? $this->successResponse($result['session'], 'Harvest already saved')
                : $this->successResponse($result['session'], 'Harvest saved successfully', 201);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('One or more hives were not found', 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse('Validation failed', 422, ['products' => [$e->getMessage()]]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save harvest', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function list(Request $request): JsonResponse
    {
        $farmIds = Farm::farmerOwned($request->user()->id)->pluck('id');
        $hiveIds = Hive::query()->whereIn('farm_id', $farmIds)->pluck('id');

        $rows = Production::query()
            ->where('productionable_type', (new Hive)->getMorphClass())
            ->whereIn('productionable_id', $hiveIds)
            ->whereNotNull('trace_number')
            ->with([
                'productionable',
                // Only live sales count as sold — a voided sale puts the
                // harvest back on the shelf.
                'saleItems' => fn ($q) => $q->whereHas('sale', fn ($s) => $s->where('status', '!=', Sale::STATUS_VOID)),
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $showsMoney = (bool) $request->user()?->canViewFinances();

        $sessions = $rows->groupBy('trace_number')->map(function ($group, $traceNumber) use ($showsMoney) {
            // A session covers several hives and products, so it can be
            // partly sold. Counting production rows rather than quantities
            // keeps this honest across mixed units (kg of honey, g of pollen).
            $soldRows = $group->filter(fn ($row) => $row->saleItems->isNotEmpty());
            $saleStatus = match (true) {
                $soldRows->isEmpty() => 'unsold',
                $soldRows->count() === $group->count() => 'sold',
                default => 'part',
            };

            return [
                'sale_status' => $saleStatus,
                'sold_line_count' => $soldRows->count(),
                'line_count' => $group->count(),
                // The money stays with owners and managers.
                'sale_total' => $showsMoney
                    ? round($group->flatMap->saleItems->sum('line_total'), 2)
                    : null,
                // The session uuid doubles as the record key for the
                // offline cache on the frontend.
                'uuid' => $traceNumber,
                'trace_number' => $traceNumber,
                'date' => $group->max('date')?->toDateString(),
                'hive_count' => $group->pluck('productionable_id')->unique()->count(),
                'hive_codes' => $group->pluck('productionable.code')->filter()->unique()->values(),
                'totals' => $group->groupBy(fn ($row) => $row->name.'|'.$row->unit)
                    ->map(fn ($products) => [
                        'product' => $products->first()->name,
                        'unit' => $products->first()->unit,
                        'quantity' => round($products->sum('quantity'), 2),
                    ])->values(),
                'notes' => $group->first()->notes,
            ];
        })->sortByDesc('date')->values();

        return $this->successResponse($sessions, 'Harvest sessions retrieved successfully');
    }
}
