<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreProductionRequest;
use App\Http\Resources\Farms\Farm\ProductionResource;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Planting;
use App\Models\Core\Production;
use App\Services\Production\ProductionExpenseRecorder;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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

    protected function resolveProductionable(string $type, string $uuid): Model
    {
        return match ($type) {
            'planting' => Planting::query()->where('uuid', $uuid)->firstOrFail(),
            'animal_group' => AnimalGroup::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported production target.'),
        };
    }
}
