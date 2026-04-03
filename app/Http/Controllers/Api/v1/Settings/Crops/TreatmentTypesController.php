<?php

namespace App\Http\Controllers\Api\v1\Settings\Crops;

use App\Http\Controllers\Controller;
use App\Models\Core\TreatmentType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TreatmentTypesController extends Controller
{
    use ApiResponse;

    public function create(Request $request, $treatmentTypeUuid = null)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:crop,livestock,general',
        ]);

        try {
            $existing = TreatmentType::where('name', request('name'))
                ->where('type', request('type'))
                ->first();

            if ($existing) {
                if ($treatmentTypeUuid && $existing->uuid == $treatmentTypeUuid) {
                    $treatmentType = TreatmentType::updateOrCreate(['uuid' => $treatmentTypeUuid], [
                        'uuid' => Str::orderedUuid(),
                        'name' => request('name'),
                        'description' => request('description'),
                        'type' => request('type'),
                    ]);

                    return $this->successResponse($treatmentType, 'Treatment type updated successfully', 201);
                }

                return $this->errorResponse('Treatment type with this name already exists for the selected type', 409);
            }

            $treatmentType = TreatmentType::create([
                'uuid' => Str::orderedUuid(),
                'name' => request('name'),
                'description' => request('description'),
                'type' => request('type'),
                'status' => 1,
            ]);

            return $this->successResponse($treatmentType, 'Treatment type created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create treatment type', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listTreatmentTypes($type = 'crop')
    {
        $treatmentTypes = TreatmentType::where('type',$type)->select('id', 'uuid', 'name', 'description', 'type', 'status')->get();

        return $this->successResponse($treatmentTypes, 'Treatment types retrieved successfully', 200);
    }

    public function delete($treatmentTypeUuid)
    {
        $treatmentType = TreatmentType::where('uuid', $treatmentTypeUuid)->first();

        if (! $treatmentType) {
            return $this->errorResponse('Treatment type not found', 404);
        }

        try {
            $treatmentType->delete();

            return $this->successResponse(null, 'Treatment type deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete treatment type', 500, ['exception' => $e->getMessage()]);
        }
    }
}
