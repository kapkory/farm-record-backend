<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Models\Core\Farm;
use App\Models\Core\Field;
use App\Models\Core\Planting;
use App\Repositories\SearchRepo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlantingsController extends Controller
{
    use ApiResponse;

    /**
         * return planting's index view
         */
    public function index(){
        return view($this->folder.'index',[

        ]);
    }

    /**
     * store planting
     */
    public function storePlanting(Request $request){
        $data = $request->validate([
            'farm_uuid' => 'required|uuid|exists:farms,uuid',
            'crop_id' => 'required|integer|exists:crops,id',
            'date_planted' => 'required|date',
            'purpose' => 'string|required'
        ]);

        unset($data['farm_uuid']);
        unset($data['field_uuid']);
        try{
            $farmId = Farm::where('uuid', $request->input('farm_uuid'))->first()->id;
            if (\request('field_uuid') != '')
                $data['field_id'] = Field::where('uuid', $request->input('field_uuid'))->first()->id;

            if (\request('variety_id') != '')
                $data['crop_variety_id'] = $request->input('variety_id');

            $data['farm_id'] = $farmId;
            $data['description'] = \request('description');
            $data['quantity_planted'] = \request('quantity_planted');
            if(!isset($data['user_id'])) {
                if (Schema::hasColumn('plantings', 'user_id'))
                    $data['user_id'] = request()->user()->id;
            }
            if (!$request->id)
                $data['uuid'] =  Str::orderedUuid();
            $planting = Planting::create($data);
            return $this->successResponse($planting, 'Field created successfully', 201);
        } catch (\Throwable $e) {
             return $this->errorResponse('Failed to save field', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * return planting values
     */
    public function listPlantings($farm_uuid = null){
//        return ["hello"];
        $query = Planting::leftJoin('crops', 'crops.id', '=', 'plantings.crop_id')
            ->leftJoin('crop_varieties', 'crop_varieties.id', '=', 'plantings.crop_variety_id')
            ->leftJoin('fields', 'fields.id', '=', 'plantings.field_id')
        ->select('plantings.id', 'plantings.uuid', 'plantings.farm_id', "date_planted","purpose","crops.name as crop",
            "crop_varieties.name as variety","fields.name as field",
            "expected_harvest_date","actual_harvest_date","quantity_planted","plantings.created_at");
        if ($farm_uuid) {
            $farmId = Farm::where('uuid', $farm_uuid)->first()->id;
            $query->where('plantings.farm_id', $farmId);
        }

        $plantings = $query->orderBy('created_at')->get();
        return $this->successResponse($plantings, 'Fields retrieved successfully', 200);
    }

    /**
     * delete planting
     */
    public function destroyPlanting($planting_id)
    {
        $planting = Planting::findOrFail($planting_id);
        $planting->delete();
        return redirect()->back()->with('notice',['type'=>'success','message'=>'Planting deleted successfully']);
    }

}
