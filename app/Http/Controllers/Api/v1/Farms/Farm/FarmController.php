<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Farms\Farm\FarmResource;
use App\Models\Core\Farm;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    use ApiResponse;
    public function show($farm_uuid)
    {
        $farm = Farm::where('uuid', $farm_uuid)->firstOrFail();
       try {
           return $this->successResponse(
               new FarmResource($farm->load(['plantings', 'fields'])),
               'Farm details retrieved successfully'
           );
       } catch (\Throwable $e) {
           return $this->errorResponse('Failed to fetch farm', 500, ['exception' => $e->getMessage()]);
       }
    }
}
