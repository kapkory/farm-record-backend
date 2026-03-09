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
    public function listPlantings(){
        $plantings = Planting::where([
            ['id','>',0]
        ]);
        if(\request('all'))
            return $plantings->get();
        return SearchRepo::of($plantings)
            ->addColumn('action',function($planting){
                $str = '';
                $json = json_encode($planting);
                $str.='<a href="#" data-model="'.htmlentities($json, ENT_QUOTES, 'UTF-8').'" onclick="prepareEdit(this,\'planting_modal\');" class="btn badge btn-info btn-sm"><i class="fa fa-edit"></i> Edit</a>';
            //    $str.='&nbsp;&nbsp;<a href="#" onclick="deleteItem(\''.url(request()->user()->role.'/plantings/delete').'\',\''.$planting->id.'\');" class="btn badge btn-outline-danger btn-sm"><i class="fa fa-trash"></i> Delete</a>';
                return $str;
            })->make();
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
