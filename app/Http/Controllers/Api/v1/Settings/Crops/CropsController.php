<?php

namespace App\Http\Controllers\Api\v1\Settings\Crops;

use App\Http\Controllers\Controller;
use App\Models\Core\Crop;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CropsController extends Controller
{
    use ApiResponse;

    public function create(Request $request, $cropUuid = null)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $existing = Crop::where('name', request('name'))->first();
            if ($existing) {
                if ($cropUuid && $existing->uuid == $cropUuid) {
                    // If updating and the name belongs to the same crop type, allow it
                    $cropType = Crop::updateOrCreate(['uuid' => $cropUuid], [
                        'name' => request('name'),
                        'description' => request('description'),
                    ]);

                    return $this->successResponse($cropType, 'Crop updated successfully', 201);
                } else {
                    return $this->errorResponse('Crop with this name already exists', 409);
                }
            }

            $cropType = Crop::create([
                'uuid' => Str::orderedUuid(),
                'name' => request('name'),
                'description' => request('description'),
            ]);

            return $this->successResponse($cropType, 'Crop created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create crop', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listCrops()
    {
        $cropTypes = Crop::select('id', 'uuid', 'name', 'description')->get();

        return $this->successResponse($cropTypes, 'Crop retrieved successfully', 200);
    }

    public function delete($cropUuid)
    {
        $crop = Crop::where('uuid', $cropUuid)->first();
        if (! $crop) {
            return $this->errorResponse('Crop not found', 404);
        }

        try {
            $crop->delete();

            return $this->successResponse(null, 'Crop deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete crop', 500, ['exception' => $e->getMessage()]);
        }
    }
}
