<?php

namespace App\Http\Controllers\Api\v1\Settings\Animals;

use App\Http\Controllers\Controller;
use App\Models\Core\AnimalType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnimalTypesController extends Controller
{
    use ApiResponse;

    public function create(Request $request, ?string $uuid = null)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:livestock,poultry,apiculture,aquaculture'],
            'tracking_mode' => ['required', 'in:group_only,individual_only,both'],
            'count_label' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        try {
            $duplicate = AnimalType::query()->where('name', $validated['name']);
            if ($uuid) {
                $duplicate->where('uuid', '!=', $uuid);
            }

            if ($duplicate->exists()) {
                return $this->errorResponse('Animal type with this name already exists', 409);
            }

            $animalType = AnimalType::updateOrCreate(
                ['uuid' => $uuid ?: (string) Str::orderedUuid()],
                [
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'tracking_mode' => $validated['tracking_mode'],
                    'count_label' => $validated['count_label'],
                    'description' => $validated['description'] ?? null,
                    'status' => $validated['status'] ?? 1,
                ]
            );

            return $this->successResponse($animalType, $uuid ? 'Animal type updated successfully' : 'Animal type created successfully', $uuid ? 200 : 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save animal type', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listAnimalTypes()
    {
        $animalTypes = AnimalType::query()
            ->select('id', 'uuid', 'name', 'category', 'tracking_mode', 'count_label', 'description', 'status')
            ->orderBy('name')
            ->get();

        return $this->successResponse($animalTypes, 'Animal types retrieved successfully');
    }

    public function delete(string $uuid)
    {
        $animalType = AnimalType::where('uuid', $uuid)->first();
        if (! $animalType) {
            return $this->errorResponse('Animal type not found', 404);
        }

        try {
            $animalType->delete();
            return $this->successResponse(null, 'Animal type deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete animal type', 500, ['exception' => $e->getMessage()]);
        }
    }
}

