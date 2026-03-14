<?php

namespace App\Http\Controllers\Api\v1\Settings\System;

use App\Http\Controllers\Controller;
use App\Models\Core\TransactionCategory;
use App\Repositories\SearchRepo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransactionsCategoryController extends Controller
{

    use ApiResponse;
    /**
     * store transaction category
     */
    public function storeTransactionCategory(Request $request,$transaction_category_uuid = null){
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        try{
            $slug = Str::slug(request('name'));
            $existing = TransactionCategory::where('slug', $slug)->first();
            if ($existing) {
                // If updating and the name belongs to the same crop type, allow it
                $cropType = TransactionCategory::updateOrCreate(['uuid' => $transaction_category_uuid], [
                    'name' => request('name'),
                    'parent_id' => request('parent_id'),
                    'type' => request('type'),
                    'description' => request('description'),
                ]);
                return $this->successResponse($cropType, 'Crop updated successfully', 201);
            }

            $cropType = TransactionCategory::create([
                'name' => request('name'),
                'slug' => $slug,
                'uuid' => Str::orderedUuid(),
                'parent_id' => request('parent_id'),
                'type' => request('type'),
                'description' => request('description'),
            ]);
            return $this->successResponse($cropType, ucwords($request->type).' created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create Transaction Type', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * return transactioncategory values
     */
    public function listTransactionCategories(){
        $transactioncategories = TransactionCategory::where([
            ['id','>',0]
        ]);
        if(\request('all'))
            return $transactioncategories->get();
        return SearchRepo::of($transactioncategories)
            ->addColumn('action',function($transactioncategory){
                $str = '';
                $json = json_encode($transactioncategory);
                $str.='<a href="#" data-model="'.htmlentities($json, ENT_QUOTES, 'UTF-8').'" onclick="prepareEdit(this,\'transactioncategory_modal\');" class="btn badge btn-info btn-sm"><i class="fa fa-edit"></i> Edit</a>';
            //    $str.='&nbsp;&nbsp;<a href="#" onclick="deleteItem(\''.url(request()->user()->role.'/transactioncategories/delete').'\',\''.$transactioncategory->id.'\');" class="btn badge btn-outline-danger btn-sm"><i class="fa fa-trash"></i> Delete</a>';
                return $str;
            })->make();
    }

    /**
     * delete transactioncategory
     */
    public function destroyTransactionCategory($transactioncategory_id)
    {
        $transactioncategory = TransactionCategory::findOrFail($transactioncategory_id);
        $transactioncategory->delete();
        return redirect()->back()->with('notice',['type'=>'success','message'=>'TransactionCategory deleted successfully']);
    }

}
