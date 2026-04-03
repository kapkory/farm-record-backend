<?php

namespace App\Http\Controllers\Api\v1\Settings\Animals;

use App\Http\Controllers\Controller;
use App\Http\Resources\Settings\Animals\AnimalBreedResource;
use App\Models\Core\AnimalBreed;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnimalBreedsController extends Controller
{
    use ApiResponse;

    public function create(Request $request, ?string $uuid = null)
    {
        $validated = $request->validate([
            'animal_type_id' => ['required', 'integer', 'exists:animal_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'in:meat,dairy,eggs,honey,wool,breeding,dual,other'],
            'average_lifespan_months' => ['nullable', 'integer', 'min:1'],
            'gestation_days' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        try {
            $duplicate = AnimalBreed::query()
                ->where('animal_type_id', $validated['animal_type_id'])
                ->where('name', $validated['name']);

            if ($uuid) {
                $duplicate->where('uuid', '!=', $uuid);
            }

            if ($duplicate->exists()) {
                return $this->errorResponse('Animal breed with this name already exists for the selected type', 409);
            }

            $breed = AnimalBreed::updateOrCreate(
                ['uuid' => $uuid ?: (string) Str::orderedUuid()],
                [
                    'animal_type_id' => $validated['animal_type_id'],
                    'name' => $validated['name'],
                    'purpose' => $validated['purpose'] ?? 'dual',
                    'average_lifespan_months' => $validated['average_lifespan_months'] ?? null,
                    'gestation_days' => $validated['gestation_days'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'status' => $validated['status'] ?? 1,
                ]
            );

            return $this->successResponse($breed, $uuid ? 'Animal breed updated successfully' : 'Animal breed created successfully', $uuid ? 200 : 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save animal breed', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listAnimalBreeds(Request $request)
    {
        $query = AnimalBreed::query()
            ->with('animalType:id,name')
            ->select('id', 'uuid', 'animal_type_id', 'name', 'purpose', 'average_lifespan_months', 'gestation_days', 'description', 'status');

        if ($request->filled('animal_type_id')) {
            $query->where('animal_type_id', $request->input('animal_type_id'));
        }

        $breeds = $query->orderBy('name')->get();

        return $this->successResponse(AnimalBreedResource::collection($breeds), 'Animal breeds retrieved successfully');
    }

    public function delete(string $uuid)
    {
        $breed = AnimalBreed::where('uuid', $uuid)->first();
        if (! $breed) {
            return $this->errorResponse('Animal breed not found', 404);
        }

        try {
            $breed->delete();
            return $this->successResponse(null, 'Animal breed deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete animal breed', 500, ['exception' => $e->getMessage()]);
        }
    }
}

