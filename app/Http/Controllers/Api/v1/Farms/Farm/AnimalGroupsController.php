<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreAnimalGroupRequest;
use App\Http\Resources\Farms\Farm\AnimalGroupResource;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Field;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AnimalGroupsController extends Controller
{
    use ApiResponse;

    public function storeAnimalGroup(StoreAnimalGroupRequest $request): JsonResponse
    {
        try {
            $farm = Farm::where('uuid', $request->validated('farm_uuid'))->firstOrFail();
            $field = $request->filled('field_uuid')
                ? Field::where('uuid', $request->validated('field_uuid'))->first()
                : null;

            $group = AnimalGroup::create([
                'uuid' => (string) Str::orderedUuid(),
                'farm_id' => $farm->id,
                'field_id' => $field?->id,
                'animal_type_id' => $request->validated('animal_type_id'),
                'animal_breed_id' => $request->validated('animal_breed_id'),
                'name' => $request->validated('name'),
                'initial_count' => $request->validated('initial_count'),
                'current_count' => $request->validated('initial_count'),
                'acquired_date' => $request->validated('acquired_date'),
                'acquisition_type' => $request->validated('acquisition_type') ?? 'purchased',
                'purpose' => $request->validated('purpose') ?? 'commercial',
                'description' => $request->validated('description') ?? null,
                'user_id' => $request->user()->id,
                'status' => $request->validated('status') ?? 1,
            ])->load(['animalType', 'animalBreed', 'field', 'animals', 'events']);

            return $this->successResponse(new AnimalGroupResource($group), 'Animal group created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create animal group', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listAnimalGroups(?string $farm_uuid = null): JsonResponse
    {
        $farmIds = Farm::farmerOwned(auth()->id())->pluck('id');

        $query = AnimalGroup::query()
            ->with(['animalType', 'animalBreed', 'field'])
            ->withCount(['animals', 'events'])
            ->whereIn('farm_id', $farmIds);

        if ($farm_uuid) {
            $farm = Farm::where('uuid', $farm_uuid)->whereIn('id', $farmIds)->first();
            if (! $farm) {
                return $this->errorResponse('Farm not found or access denied', 404);
            }
            $query->where('farm_id', $farm->id);
        }

        $groups = $query->orderByDesc('created_at')->get();

        return $this->successResponse(AnimalGroupResource::collection($groups), 'Animal groups retrieved successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $group = AnimalGroup::with(['animalType', 'animalBreed', 'field', 'animals', 'events'])
            ->where('uuid', $uuid)
            ->first();

        if (! $group || ! Farm::farmerOwned(auth()->id())->where('id', $group->farm_id)->exists()) {
            return $this->errorResponse('Animal group not found', 404);
        }

        return $this->successResponse(new AnimalGroupResource($group), 'Animal group retrieved successfully');
    }

    public function update(StoreAnimalGroupRequest $request, string $uuid): JsonResponse
    {
        $group = AnimalGroup::where('uuid', $uuid)->first();
        if (! $group) {
            return $this->errorResponse('Animal group not found', 404);
        }

        try {
            $farm = Farm::where('uuid', $request->validated('farm_uuid'))->firstOrFail();
            $field = $request->filled('field_uuid')
                ? Field::where('uuid', $request->validated('field_uuid'))->first()
                : null;

            $group->update([
                'farm_id' => $farm->id,
                'field_id' => $field?->id,
                'animal_type_id' => $request->validated('animal_type_id'),
                'animal_breed_id' => $request->validated('animal_breed_id'),
                'name' => $request->validated('name'),
                'initial_count' => $request->validated('initial_count'),
                'current_count' => max($group->current_count, $request->validated('initial_count')),
                'acquired_date' => $request->validated('acquired_date'),
                'acquisition_type' => $request->validated('acquisition_type') ?? $group->acquisition_type,
                'purpose' => $request->validated('purpose') ?? $group->purpose,
                'description' => $request->validated('description') ?? null,
                'status' => $request->validated('status') ?? $group->status,
            ]);

            return $this->successResponse(new AnimalGroupResource($group->load(['animalType', 'animalBreed', 'field', 'animals', 'events'])), 'Animal group updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update animal group', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        $group = AnimalGroup::where('uuid', $uuid)->first();
        if (! $group) {
            return $this->errorResponse('Animal group not found', 404);
        }

        try {
            $group->delete();
            return $this->successResponse(null, 'Animal group deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete animal group', 500, ['exception' => $e->getMessage()]);
        }
    }
}

