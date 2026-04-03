<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreAnimalRequest;
use App\Http\Resources\Farms\Farm\AnimalResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AnimalsController extends Controller
{
    use ApiResponse;

    public function store(StoreAnimalRequest $request): JsonResponse
    {
        try {
            $group = $request->filled('animal_group_uuid')
                ? AnimalGroup::with('farm')->where('uuid', $request->validated('animal_group_uuid'))->firstOrFail()
                : null;
            $farm = $group?->farm ?? ($request->filled('farm_uuid')
                ? Farm::where('uuid', $request->validated('farm_uuid'))->firstOrFail()
                : null);

            $animal = Animal::create([
                'uuid' => (string) Str::orderedUuid(),
                'farm_id' => $farm->id,
                'animal_group_id' => $group?->id,
                'animal_type_id' => $group?->animal_type_id ?? $request->validated('animal_type_id'),
                'animal_breed_id' => $request->validated('animal_breed_id') ?? $group?->animal_breed_id,
                'tag_id' => $request->validated('tag_id') ?? null,
                'name' => $request->validated('name') ?? null,
                'gender' => $request->validated('gender') ?? 'unknown',
                'date_of_birth' => $request->validated('date_of_birth') ?? null,
                'acquisition_date' => $request->validated('acquisition_date') ?? null,
                'acquisition_type' => $request->validated('acquisition_type') ?? 'born',
                'weight' => $request->validated('weight') ?? null,
                'weight_unit' => $request->validated('weight_unit') ?? 'kg',
                'status' => $request->validated('status') ?? 'active',
                'notes' => $request->validated('notes') ?? null,
                'user_id' => $request->user()->id,
            ])->load(['farm', 'animalGroup', 'animalType', 'animalBreed']);

            return $this->successResponse(new AnimalResource($animal), 'Animal saved successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save animal', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listByGroup(string $group_uuid): JsonResponse
    {
        $group = AnimalGroup::where('uuid', $group_uuid)->first();
        if (! $group || ! Farm::farmerOwned(auth()->id())->where('id', $group->farm_id)->exists()) {
            return $this->errorResponse('Animal group not found', 404);
        }

        $animals = Animal::with(['farm', 'animalGroup', 'animalType', 'animalBreed'])
            ->where('animal_group_id', $group->id)
            ->orderBy('name')
            ->get();

        return $this->successResponse(AnimalResource::collection($animals), 'Animals retrieved successfully');
    }

    public function listStandalone(string $farm_uuid): JsonResponse
    {
        $farm = Farm::where('uuid', $farm_uuid)->first();
        if (! $farm || ! Farm::farmerOwned(auth()->id())->where('id', $farm->id)->exists()) {
            return $this->errorResponse('Farm not found', 404);
        }

        $animals = Animal::with(['farm', 'animalGroup', 'animalType', 'animalBreed'])
            ->standalone()
            ->forFarm($farm->id)
            ->orderBy('name')
            ->get();

        return $this->successResponse(AnimalResource::collection($animals), 'Standalone animals retrieved successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $animal = Animal::with(['farm', 'animalGroup', 'animalType', 'animalBreed', 'events'])
            ->where('uuid', $uuid)
            ->first();

        if (! $animal || ! Farm::farmerOwned(auth()->id())->where('id', $animal->farm_id)->exists()) {
            return $this->errorResponse('Animal not found', 404);
        }

        return $this->successResponse(new AnimalResource($animal), 'Animal retrieved successfully');
    }

    public function update(StoreAnimalRequest $request, string $uuid): JsonResponse
    {
        $animal = Animal::with('animalGroup', 'farm')->where('uuid', $uuid)->first();
        if (! $animal) {
            return $this->errorResponse('Animal not found', 404);
        }

        try {
            $group = $request->filled('animal_group_uuid')
                ? AnimalGroup::with('farm')->where('uuid', $request->validated('animal_group_uuid'))->firstOrFail()
                : $animal->animalGroup;
            $farm = $group?->farm ?? ($request->filled('farm_uuid')
                ? Farm::where('uuid', $request->validated('farm_uuid'))->firstOrFail()
                : $animal->farm);

            $animal->update([
                'farm_id' => $farm->id,
                'animal_group_id' => $group?->id,
                'animal_type_id' => $group?->animal_type_id ?? ($request->validated('animal_type_id') ?? $animal->animal_type_id),
                'animal_breed_id' => $request->validated('animal_breed_id') ?? $group?->animal_breed_id,
                'tag_id' => $request->validated('tag_id') ?? null,
                'name' => $request->validated('name') ?? null,
                'gender' => $request->validated('gender') ?? 'unknown',
                'date_of_birth' => $request->validated('date_of_birth') ?? null,
                'acquisition_date' => $request->validated('acquisition_date') ?? null,
                'acquisition_type' => $request->validated('acquisition_type') ?? $animal->acquisition_type,
                'weight' => $request->validated('weight') ?? null,
                'weight_unit' => $request->validated('weight_unit') ?? 'kg',
                'status' => $request->validated('status') ?? $animal->status,
                'notes' => $request->validated('notes') ?? null,
            ]);

            return $this->successResponse(new AnimalResource($animal->load(['farm', 'animalGroup', 'animalType', 'animalBreed'])), 'Animal updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update animal', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        $animal = Animal::where('uuid', $uuid)->first();
        if (! $animal) {
            return $this->errorResponse('Animal not found', 404);
        }

        try {
            $animal->delete();
            return $this->successResponse(null, 'Animal deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete animal', 500, ['exception' => $e->getMessage()]);
        }
    }
}

