<?php

namespace App\Http\Controllers\Api\v1\Settings\Crops;

use App\Http\Controllers\Controller;
use App\Models\Core\Crop;
use App\Models\Core\CropVariety;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VarietiesController extends Controller
{
    use ApiResponse;

    /**
     * Create or update a crop variety.
     *
     * If $varietyUuid is provided, the record is updated (or created if missing).
     */
    public function create(Request $request, $varietyUuid = null)
    {
        $validated = $request->validate([
            'crop_id' => 'required|exists:crops,id',
            'name' => 'required|string|max:255',
            'maturity_days' => 'required|integer|max:255',
            'expected_yield' => 'nullable|integer|max:255',
            'description' => 'nullable|string',
            'harvest_type' => 'nullable|string|max:255',
        ]);

        try {
            // Duplicate prevention (per crop): same crop_id + name.
            $duplicate = CropVariety::query()
                ->where('crop_id', $validated['crop_id'])
                ->where('name', $validated['name']);

            if ($varietyUuid) {
                $duplicate->where('uuid', '!=', $varietyUuid);
            }

            if ($duplicate->exists()) {
                return $this->errorResponse(
                    'Crop variety with this name already exists for the selected crop',
                    409
                );
            }

            // Update (or create) by uuid.
            $variety = CropVariety::updateOrCreate(
                ['uuid' => $varietyUuid ?: (string) Str::orderedUuid()],
                [
                    'crop_id' => $validated['crop_id'],
                    'name' => $validated['name'],
                    'maturity_days' => $validated['maturity_days'],
                    'expected_yield' => $validated['expected_yield'],
                    'description' => $validated['description'],
                    'harvest_type' => $validated['harvest_type'],
                ]
            );

            return $this->successResponse(
                $variety,
                $varietyUuid ? 'Crop variety updated successfully' : 'Crop variety created successfully',
                $varietyUuid ? 200 : 201
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save crop variety', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listVarieties(Request $request)
    {
        $query = CropVariety::query();

        if (Schema::hasColumn('crop_varieties', 'uuid')) {
            $query->select('id', 'uuid', 'crop_id', 'name', 'maturity_days', 'expected_yield', 'harvest_type', 'description');
        } else {
            $query->select('id', 'crop_id', 'name', 'maturity_days', 'expected_yield', 'harvest_type', 'description');
        }

        if ($request->filled('crop_id')) {
            $query->where('crop_id', $request->input('crop_id'));
        }

        $varieties = $query->orderBy('name')->get();

        return $this->successResponse($varieties, 'Crop varieties retrieved successfully', 200);
    }

    /**
     * Backwards-compatible endpoint name (if routes still call listCrops).
     */
    public function listCrops(Request $request)
    {
        return $this->listVarieties($request);
    }

    public function delete(string $varietyUuid)
    {
        $variety = CropVariety::where('uuid', $varietyUuid)->first();
        if (! $variety) {
            return $this->errorResponse('Crop variety not found', 404);
        }

        try {
            $variety->delete();
            return $this->successResponse(null, 'Crop variety deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete crop variety', 500, ['exception' => $e->getMessage()]);
        }
    }
}
