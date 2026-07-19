<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreProductionRequest;
use App\Http\Resources\Farms\Farm\ProductionResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\FarmerUser;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use App\Models\Core\Production;
use App\Models\Core\Sale;
use App\Models\Core\SaleItem;
use App\Services\Production\ProductionExpenseRecorder;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function __construct(protected ProductionExpenseRecorder $productionExpenseRecorder) {}

    public function store(StoreProductionRequest $request): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Production::class,
            fn (Production $production) => $production->user_id === $request->user()->id
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new ProductionResource($existing->load('productionable')),
                'Production already saved'
            );
        }

        try {
            $productionable = $this->resolveProductionable(
                $request->validated('productionable_type'),
                $request->validated('productionable_uuid')
            );

            $validated = $request->validated();

            $production = DB::transaction(function () use ($request, $productionable, $validated, $uuid) {
                $production = Production::create([
                    'uuid' => $uuid,
                    'productionable_type' => $productionable::class,
                    'productionable_id' => $productionable->getKey(),
                    'name' => $validated['name'],
                    'date' => $validated['date'],
                    'trace_number' => $validated['trace_number'] ?? null,
                    'quantity' => $validated['quantity'],
                    'unit' => $validated['unit'],
                    'grade' => $validated['grade'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'user_id' => $request->user()->id,
                ])->load('productionable');

                if (($validated['record_expense'] ?? false) === true && $productionable instanceof Planting) {
                    $this->productionExpenseRecorder->recordForPlanting($request->user(), $productionable->load('farm'), $validated);
                }

                return $production;
            });

            return $this->successResponse(new ProductionResource($production), 'Production saved successfully', 201);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Production::class, $uuid)) {
                return $this->successResponse(
                    new ProductionResource($replayed->load('productionable')),
                    'Production already saved'
                );
            }

            return $this->errorResponse('Failed to save production', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listHarvests($productionableUuid): JsonResponse
    {
        $type = request()->query('productionable_type', 'planting');
        $productionable = $this->resolveProductionable($type, $productionableUuid);

        $productions = Production::query()
            ->with('productionable')
            ->where('productionable_type', $productionable::class)
            ->where('productionable_id', $productionable->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            ProductionResource::collection($productions),
            'Productions retrieved successfully'
        );
    }

    // GET /unlinked?product=honey&sellable_type=hive&sellable_uuid=…
    // Recent collections not yet attached to any sale line — feeds the
    // "from which collection?" picker in the Record Sale form. The product
    // filter keeps honey sales from offering egg collections.
    public function listUnlinked(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');
        $memberUserIds = FarmerUser::query()->whereIn('farmer_id', $farmerIds)->pluck('user_id');

        $query = Production::query()
            ->with('productionable')
            ->whereDoesntHave('saleItems')
            ->whereIn('user_id', $memberUserIds);

        if ($request->filled('product')) {
            // Spaces/underscores become wildcards so 'Comb honey' matches 'comb_honey'.
            $pattern = '%'.preg_replace('/[\s_]+/', '%', strtolower(trim($request->input('product')))).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$pattern]);
        }

        if ($request->filled('sellable_type') && $request->filled('sellable_uuid')) {
            $sellable = $this->resolveProductionable(
                $request->input('sellable_type'),
                $request->input('sellable_uuid')
            );
            // Some writers store the morph alias (bee harvests → 'hive'),
            // others the FQCN — match either.
            $query->whereIn('productionable_type', [$sellable->getMorphClass(), $sellable::class])
                ->where('productionable_id', $sellable->getKey());
        }

        $productions = $query->orderByDesc('date')->orderByDesc('id')->limit(10)->get();

        return $this->successResponse(
            ProductionResource::collection($productions),
            'Unlinked productions retrieved successfully'
        );
    }

    // GET /summary?from=&to= — produced vs sold per product ("where did the
    // milk go"). Product names are matched case-insensitively with
    // underscores treated as spaces, so 'comb_honey' collections line up
    // with 'Comb honey' sale lines.
    public function productionSummary(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');
        $memberUserIds = FarmerUser::query()->whereIn('farmer_id', $farmerIds)->pluck('user_id');

        $produced = Production::query()
            ->whereIn('user_id', $memberUserIds)
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->input('to')))
            ->selectRaw("LOWER(REPLACE(name, '_', ' ')) as product_key, MAX(unit) as unit, COALESCE(SUM(quantity), 0) as total")
            ->groupBy('product_key')
            ->get();

        $sold = SaleItem::query()
            ->whereHas('sale', function ($q) use ($request, $farmerIds) {
                $q->whereIn('farmer_id', $farmerIds)->where('status', '!=', Sale::STATUS_VOID);
                if ($request->filled('from')) {
                    $q->whereDate('date', '>=', $request->input('from'));
                }
                if ($request->filled('to')) {
                    $q->whereDate('date', '<=', $request->input('to'));
                }
            })
            ->selectRaw("LOWER(REPLACE(product, '_', ' ')) as product_key, MAX(unit) as unit, COALESCE(SUM(quantity), 0) as total")
            ->groupBy('product_key')
            ->get();

        $rows = [];
        foreach ($produced as $row) {
            $rows[$row->product_key] = [
                'product' => $row->product_key,
                'unit' => $row->unit,
                'produced' => (float) $row->total,
                'sold' => 0.0,
            ];
        }
        foreach ($sold as $row) {
            $rows[$row->product_key] ??= [
                'product' => $row->product_key,
                'unit' => $row->unit,
                'produced' => 0.0,
                'sold' => 0.0,
            ];
            $rows[$row->product_key]['sold'] = (float) $row->total;
        }

        $rows = array_map(function (array $row) {
            $row['unsold'] = max(0, round($row['produced'] - $row['sold'], 2));

            return $row;
        }, array_values($rows));

        usort($rows, fn ($a, $b) => $b['produced'] <=> $a['produced']);

        return $this->successResponse($rows, 'Production summary retrieved successfully');
    }

    protected function resolveProductionable(string $type, string $uuid): Model
    {
        return match ($type) {
            'planting' => Planting::query()->where('uuid', $uuid)->firstOrFail(),
            'animal_group' => AnimalGroup::query()->where('uuid', $uuid)->firstOrFail(),
            'animal' => Animal::query()->where('uuid', $uuid)->firstOrFail(),
            'hive' => Hive::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported production target.'),
        };
    }
}
