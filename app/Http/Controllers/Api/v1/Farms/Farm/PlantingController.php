<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Farms\Farm\PlantingResource;
use App\Models\Core\Planting;
use Illuminate\Http\Request;

class PlantingController extends Controller
{
    public function show($planting_uuid){
        $planting = Planting::where('uuid', $planting_uuid)->firstOrFail();
        return new PlantingResource($planting);
    }
}
