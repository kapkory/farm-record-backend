<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreProductionRequest;
use App\Http\Resources\Farms\Farm\ProductionResource;
use App\Models\Core\Planting;
use App\Models\Core\Production;
use App\Services\Production\ProductionExpenseRecorder;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductionsController extends Controller
{
    use ApiResponse;

    public function __construct(protected ProductionExpenseRecorder $productionExpenseRecorder)
    {
    }

    public function store(StoreProductionRequest $request): JsonResponse
    {
        try {
            $productionable = $this->resolveProductionable(
                $request->validated('productionable_type'),
                $request->validated('productionable_uuid')
            );

            $validated = $request->validated();

            $production = DB::transaction(function () use ($request, $productionable, $validated) {
                $production = Production::create([
                    'uuid' => (string) Str::orderedUuid(),
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
            return $this->errorResponse('Failed to save production', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listHarvests($plantingUuid): JsonResponse
    {
        $planting = Planting::query()->where('uuid', $plantingUuid)->firstOrFail();

        $productions = Production::query()
            ->with('productionable')
            ->where('productionable_type', Planting::class)
            ->where('productionable_id', $planting->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            ProductionResource::collection($productions),
            'Harvests retrieved successfully'
        );
    }

    protected function resolveProductionable(string $type, string $uuid): Model
    {
        return match ($type) {
            'planting' => Planting::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported production target.'),
        };
    }
}
