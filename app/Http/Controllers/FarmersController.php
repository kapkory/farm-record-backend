<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Core\Farmer;
use App\Repositories\SearchRepo;
use Illuminate\Support\Facades\Schema;

class FarmersController extends Controller
{
            /**
         * return farmer's index view
         */
    public function index(){
        return view($this->folder.'index',[

        ]);
    }

    /**
     * store farmer
     */
    public function storeFarmer(){
        request()->validate($this->getValidationFields());
        $data = \request()->all();
        if(!isset($data['user_id'])) {
            if (Schema::hasColumn('farmers', 'user_id'))
                $data['user_id'] = request()->user()->id;
        }

        $this->autoSaveModel($data);

        $action = \request('id') ? 'updated' : 'saved';
        return redirect()->back()->with('notice',['type'=>'success','message'=>'Farmer '.$action.' successfully']);
    }

    /**
     * return farmer values
     */
    public function listFarmers(){
        $farmers = Farmer::where([
            ['id','>',0]
        ]);
        if(\request('all'))
            return $farmers->get();
        return SearchRepo::of($farmers)
            ->addColumn('action',function($farmer){
                $str = '';
                $json = json_encode($farmer);
                $str.='<a href="#" data-model="'.htmlentities($json, ENT_QUOTES, 'UTF-8').'" onclick="prepareEdit(this,\'farmer_modal\');" class="btn badge btn-info btn-sm"><i class="fa fa-edit"></i> Edit</a>';
            //    $str.='&nbsp;&nbsp;<a href="#" onclick="deleteItem(\''.url(request()->user()->role.'/farmers/delete').'\',\''.$farmer->id.'\');" class="btn badge btn-outline-danger btn-sm"><i class="fa fa-trash"></i> Delete</a>';
                return $str;
            })->make();
    }

    /**
     * delete farmer
     */
    public function destroyFarmer($farmer_id)
    {
        $farmer = Farmer::findOrFail($farmer_id);
        $farmer->delete();
        return redirect()->back()->with('notice',['type'=>'success','message'=>'Farmer deleted successfully']);
    }

}
