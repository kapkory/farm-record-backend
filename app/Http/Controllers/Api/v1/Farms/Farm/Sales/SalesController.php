<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreSalePaymentRequest;
use App\Http\Requests\Farms\StoreSaleRequest;
use App\Http\Resources\Farms\Farm\SalePaymentResource;
use App\Http\Resources\Farms\Farm\SaleResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use App\Models\Core\Sale;
use App\Models\Core\SaleItem;
use App\Models\Core\SalePayment;
use App\Services\Sales\SaleService;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function __construct(protected SaleService $saleService) {}

    public function storeSale(StoreSaleRequest $request): JsonResponse
    {
        $user = $request->user();
        $farm = Farm::query()
            ->farmerOwned($user->id)
            ->where('uuid', $request->validated('farm_uuid'))
            ->first();

        // A bare 404 here is misleading — the usual cause is a sale queued
        // against a farm this account cannot reach (a stale offline cache, or
        // a different sign-in on the same device). Say so.
        if (! $farm) {
            return $this->errorResponse(
                'That farm is not available to your account, so this sale cannot be saved against it.',
                422,
                ['farm_uuid' => ['Choose a farm you have access to.']]
            );
        }

        // A replayed offline create must answer from the stored sale before
        // the service runs, or money would be posted twice.
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Sale::class,
            fn (Sale $sale) => $user->farmers()->where('farmers.id', $sale->farmer_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new SaleResource($existing->load(['buyer', 'items', 'payments'])),
                'Sale already recorded'
            );
        }

        try {
            $sale = $this->saleService->store($user, $farm, $request->validated(), $uuid);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Sale::class, $uuid)) {
                return $this->successResponse(
                    new SaleResource($replayed->load(['buyer', 'items', 'payments'])),
                    'Sale already recorded'
                );
            }

            return $this->errorResponse('Failed to record sale', 500, ['exception' => $e->getMessage()]);
        }

        return $this->successResponse(new SaleResource($sale), 'Sale recorded successfully', 201);
    }

    public function listSales(Request $request): JsonResponse
    {
        $query = $this->ownedSales($request)
            ->with(['buyer', 'items'])
            ->latest('date')
            ->latest('id');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('buyer_uuid')) {
            $query->whereHas('buyer', fn ($q) => $q->where('uuid', $request->input('buyer_uuid')));
        }

        return $this->successResponse(
            SaleResource::collection($query->limit(500)->get()),
            'Sales retrieved successfully'
        );
    }

    public function showSale(Request $request, string $uuid): JsonResponse
    {
        $sale = $this->ownedSales($request)
            ->with(['buyer', 'items.sellable', 'payments'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->successResponse(new SaleResource($sale), 'Sale retrieved successfully');
    }

    public function storePayment(StoreSalePaymentRequest $request, string $uuid): JsonResponse
    {
        $sale = $this->ownedSales($request)->where('uuid', $uuid)->firstOrFail();

        // Idempotent replay: a queued payment retried after a lost response
        // must not post the money twice.
        if ($clientUuid = $request->validated('uuid')) {
            $existing = SalePayment::where('uuid', $clientUuid)->first();
            if ($existing) {
                if ($existing->sale_id !== $sale->id) {
                    return $this->clientUuidTakenResponse();
                }

                return $this->successResponse(
                    new SalePaymentResource($existing),
                    'Payment already recorded'
                );
            }
        }

        $payment = $this->saleService->recordPayment($request->user(), $sale, $request->validated());

        return $this->successResponse(
            [
                'payment' => new SalePaymentResource($payment),
                'sale' => new SaleResource($sale->fresh(['buyer', 'items', 'payments'])),
            ],
            'Payment recorded successfully',
            201
        );
    }

    public function voidSale(Request $request, string $uuid): JsonResponse
    {
        $sale = $this->ownedSales($request)->where('uuid', $uuid)->firstOrFail();

        $voided = $this->saleService->void($request->user(), $sale);

        return $this->successResponse(new SaleResource($voided), 'Sale voided successfully');
    }

    // GET /summary?from=&to= — powers the sales page totals and reports.
    public function salesSummary(Request $request): JsonResponse
    {
        $base = $this->ownedSales($request)->where('status', '!=', Sale::STATUS_VOID);

        // Optional farm scope so a single farm page can show only its income.
        if ($request->filled('farm_uuid')) {
            $farm = Farm::query()
                ->farmerOwned($request->user()->id)
                ->where('uuid', $request->input('farm_uuid'))
                ->first();

            if (! $farm) {
                return $this->errorResponse('Farm not found or access denied.', 404);
            }

            $base->where('farm_id', $farm->id);
        }

        if ($request->filled('from')) {
            $base->whereDate('date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $base->whereDate('date', '<=', $request->input('to'));
        }

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(amount_total), 0) as total_amount, COALESCE(SUM(amount_paid), 0) as paid_amount, COALESCE(SUM(amount_total - amount_paid), 0) as owed_amount')
            ->first();

        $saleIds = (clone $base)->pluck('id');

        $byCategory = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('category, COALESCE(SUM(line_total), 0) as total, COALESCE(SUM(quantity), 0) as quantity')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $byProduct = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('product, unit, COALESCE(SUM(line_total), 0) as total, COALESCE(SUM(quantity), 0) as quantity')
            ->groupBy('product', 'unit')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $this->successResponse([
            'sales_count' => (int) $totals->sales_count,
            'total_amount' => (float) $totals->total_amount,
            'paid_amount' => (float) $totals->paid_amount,
            'owed_amount' => (float) $totals->owed_amount,
            'by_category' => $byCategory,
            'by_product' => $byProduct,
        ], 'Sales summary retrieved successfully');
    }

    protected function ownedSales(Request $request)
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        return Sale::query()->whereIn('farmer_id', $farmerIds);
    }

    // GET /income/{sellable_type}/{sellable_uuid} — total sale income
    // attributed to one animal/group/planting/hive, so its profitability
    // panel can count sales made through the Record Sale flow.
    public function sellableIncome(Request $request, string $sellableType, string $sellableUuid): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        $modelClass = match ($sellableType) {
            'animal' => Animal::class,
            'animal_group' => AnimalGroup::class,
            'planting' => Planting::class,
            'hive' => Hive::class,
            default => null,
        };

        if (! $modelClass) {
            return $this->errorResponse('Unsupported sale target.', 422);
        }

        $sellableId = $modelClass::where('uuid', $sellableUuid)->value('id');

        if (! $sellableId) {
            return $this->successResponse(
                ['total' => 0.0, 'count' => 0],
                'Sale income retrieved successfully'
            );
        }

        $query = SaleItem::query()
            ->where('sellable_type', $sellableType)
            ->where('sellable_id', $sellableId)
            ->whereHas('sale', fn ($q) => $q->whereIn('farmer_id', $farmerIds)->where('status', '!=', Sale::STATUS_VOID));

        return $this->successResponse([
            'total' => (float) (clone $query)->sum('line_total'),
            'count' => (clone $query)->distinct('sale_id')->count('sale_id'),
        ], 'Sale income retrieved successfully');
    }
}
