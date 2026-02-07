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

        try{
            $existing = Crop::where('name', request('name'))->first();
            if ($existing) {
                if ($cropUuid && $existing->uuid == $cropUuid) {
                    // If updating and the name belongs to the same crop type, allow it
                    $cropType = Crop::updateOrCreate(['uuid' => $cropUuid], [
                        'uuid' => Str::orderedUuid(),
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
        $cropTypes = Crop::select('id','name','description')->get();
        return $this->successResponse($cropTypes, 'Crop retrieved successfully', 200);
    }
}
