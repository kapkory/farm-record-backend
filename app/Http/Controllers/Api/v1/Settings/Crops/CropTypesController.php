<?php

namespace App\Http\Controllers\Api\v1\Settings\Crops;

use App\Http\Controllers\Controller;
use App\Models\Core\CropType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CropTypesController extends Controller
{
    use ApiResponse;
    public function create(Request $request, $cropUuid = null)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try{
            $existing = CropType::where('name', request('name'))->first();
            if ($existing) {
                if ($cropUuid && $existing->uuid == $cropUuid) {
                    // If updating and the name belongs to the same crop type, allow it
                    $cropType = CropType::updateOrCreate(['uuid' => $cropUuid], [
                        'uuid' => Str::orderedUuid(),
                        'name' => request('name'),
                        'description' => request('description'),
                    ]);
                    return $this->successResponse($cropType, 'Crop type updated successfully', 201);
                } else {
                    return $this->errorResponse('Crop type with this name already exists', 409);
                }
            }

            $cropType = CropType::create([
                'uuid' => Str::orderedUuid(),
                'name' => request('name'),
                'description' => request('description'),
            ]);
            return $this->successResponse($cropType, 'Farm created successfully', 201);
        } catch (\Throwable $e) {
             return $this->errorResponse('Failed to create farm', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listCropTypes()
    {
        $cropTypes = CropType::select('id','name','description')->get();
        return $this->successResponse($cropTypes, 'Crop types retrieved successfully', 200);
    }
}
