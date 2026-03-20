<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreProductionRequest;
use App\Http\Resources\Farms\Farm\ProductionResource;
use App\Models\Core\Production;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProductionsController extends Controller
{
    use ApiResponse;

    public function store(StoreProductionRequest $request): JsonResponse
    {
        try {
            $productionable = $this->resolveProductionable(
                $request->validated('productionable_type'),
                $request->validated('productionable_uuid')
            );

            $production = Production::create([
                'uuid' => (string) Str::orderedUuid(),
                'productionable_type' => $productionable::class,
                'productionable_id' => $productionable->getKey(),
                'name' => $request->validated('name'),
                'date' => $request->validated('date'),
                'trace_number' => $request->validated('trace_number'),
                'quantity' => $request->validated('quantity'),
                'unit' => $request->validated('unit'),
                'grade' => $request->validated('grade'),
                'notes' => $request->validated('notes'),
                'user_id' => $request->user()->id,
            ])->load('productionable');

            return $this->successResponse(new ProductionResource($production), 'Production saved successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save production', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listHarvests($plantingUuid): JsonResponse
    {
        $planting = \App\Models\Core\Planting::query()->where('uuid', $plantingUuid)->firstOrFail();

        $productions = Production::query()
            ->with('productionable')
            ->where('productionable_type', \App\Models\Core\Planting::class)
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
            'planting' => \App\Models\Core\Planting::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported production target.'),
        };
    }
}
