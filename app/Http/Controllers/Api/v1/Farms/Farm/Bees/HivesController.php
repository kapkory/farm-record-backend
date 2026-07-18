<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Bees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bees\StoreHiveRequest;
use App\Http\Requests\Bees\UpdateHiveRequest;
use App\Http\Resources\Farms\Farm\Bees\HiveResource;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Services\Bees\HiveNamingService;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HivesController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function __construct(protected HiveNamingService $namingService) {}

    public function store(StoreHiveRequest $request): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Hive::class,
            fn (Hive $hive) => Farm::farmerOwned($request->user()->id)->pluck('id')->contains($hive->farm_id)
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new HiveResource($existing->load('apiary.apiaryProfile')),
                'Hive already saved'
            );
        }

        try {
            $farmIds = Farm::farmerOwned($request->user()->id)->pluck('id');

            $apiary = AnimalGroup::query()
                ->where('uuid', $request->validated('apiary_uuid'))
                ->whereIn('farm_id', $farmIds)
                ->firstOrFail();

            $farm = Farm::query()->findOrFail($apiary->farm_id);

            if ($request->filled('farm_uuid') && $farm->uuid !== $request->validated('farm_uuid')) {
                throw new ModelNotFoundException;
            }

            $hive = DB::transaction(function () use ($request, $farm, $apiary, $uuid) {
                $allocation = $this->namingService->allocate($apiary);

                return Hive::create([
                    'uuid' => $uuid,
                    'farm_id' => $farm->id,
                    'farmer_id' => $farm->farmer_id,
                    'animal_group_id' => $apiary->id,
                    'sequence' => $allocation['sequence'],
                    'code' => $allocation['code'],
                    'name' => $request->validated('name'),
                    'hive_type' => $request->validated('hive_type'),
                    'occupancy' => $request->validated('occupancy') ?? Hive::OCCUPANCY_OCCUPIED,
                    'installed_date' => $request->validated('installed_date'),
                    'harvest_interval_days' => $request->validated('harvest_interval_days'),
                    'user_id' => $request->user()->id,
                    'notes' => $request->validated('notes'),
                ]);
            });

            return $this->successResponse(
                new HiveResource($hive->load('apiary.apiaryProfile')),
                'Hive saved successfully',
                201
            );
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Farm or apiary not found', 404);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Hive::class, $uuid)) {
                return $this->successResponse(
                    new HiveResource($replayed->load('apiary.apiaryProfile')),
                    'Hive already saved'
                );
            }

            return $this->errorResponse('Failed to save hive', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function list(Request $request): JsonResponse
    {
        $farmIds = Farm::farmerOwned($request->user()->id)->pluck('id');

        $query = Hive::query()
            ->with('apiary.apiaryProfile')
            ->whereIn('farm_id', $farmIds);

        if ($apiaryUuid = $request->query('apiary')) {
            $apiary = AnimalGroup::query()
                ->where('uuid', $apiaryUuid)
                ->whereIn('farm_id', $farmIds)
                ->first();

            if (! $apiary) {
                return $this->errorResponse('Apiary not found or access denied', 404);
            }

            $query->where('animal_group_id', $apiary->id);
        }

        if ($farmUuid = $request->query('farm')) {
            $farm = Farm::query()->where('uuid', $farmUuid)->whereIn('id', $farmIds)->first();

            if (! $farm) {
                return $this->errorResponse('Farm not found or access denied', 404);
            }

            $query->where('farm_id', $farm->id);
        }

        if ($occupancy = $request->query('occupancy')) {
            $query->where('occupancy', $occupancy);
        }

        $hives = $query->orderBy('animal_group_id')->orderBy('sequence')->get();

        return $this->successResponse(HiveResource::collection($hives), 'Hives retrieved successfully');
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $hive = $this->findOwnedHive($request->user()->id, $uuid);

        if (! $hive) {
            return $this->errorResponse('Hive not found', 404);
        }

        $hive->load([
            'apiary.apiaryProfile',
            'productions' => fn ($q) => $q->orderByDesc('date')->orderByDesc('id')->limit(20),
        ]);

        $data = (new HiveResource($hive))->resolve($request);
        $data['recent_productions'] = $hive->productions->map(fn ($production) => [
            'uuid' => $production->uuid,
            'product' => $production->name,
            'date' => $production->date?->toDateString(),
            'quantity' => (float) $production->quantity,
            'unit' => $production->unit,
            'trace_number' => $production->trace_number,
        ])->values();

        return $this->successResponse($data, 'Hive retrieved successfully');
    }

    public function update(UpdateHiveRequest $request, string $uuid): JsonResponse
    {
        $hive = $this->findOwnedHive($request->user()->id, $uuid);

        if (! $hive) {
            return $this->errorResponse('Hive not found', 404);
        }

        try {
            $hive->fill($request->validated())->save();

            return $this->successResponse(
                new HiveResource($hive->load('apiary.apiaryProfile')),
                'Hive updated successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update hive', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $hive = $this->findOwnedHive($request->user()->id, $uuid);

        if (! $hive) {
            return $this->errorResponse('Hive not found', 404);
        }

        $hive->delete();

        return $this->successResponse(null, 'Hive deleted successfully');
    }

    protected function findOwnedHive(int $userId, string $uuid): ?Hive
    {
        return Hive::query()
            ->where('uuid', $uuid)
            ->whereIn('farm_id', Farm::farmerOwned($userId)->pluck('id'))
            ->first();
    }
}
