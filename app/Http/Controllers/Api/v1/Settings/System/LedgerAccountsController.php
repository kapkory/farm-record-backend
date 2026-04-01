<?php

namespace App\Http\Controllers\Api\v1\Settings\System;

use App\Http\Controllers\Controller;
use App\Models\Core\LedgerAccount;
use App\Repositories\SearchRepo;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LedgerAccountsController extends Controller
{

    use ApiResponse;
    /**
     * store transaction category
     */
    public function storeLedgerAccount(Request $request,$transaction_category_uuid = null){
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|exists:ledger_accounts,id',
            'description' => 'nullable|string',
        ]);

        try{
            $slug = Str::slug(request('name'));
            $existing = LedgerAccount::where('slug', $slug)->first();
            if ($existing) {
                // If updating and the name belongs to the same crop type, allow it
                $cropType = LedgerAccount::updateOrCreate(['uuid' => $transaction_category_uuid], [
                    'name' => request('name'),
                    'parent_id' => request('parent_id'),
                    'type' => request('type'),
                    'description' => request('description'),
                ]);
                return $this->successResponse($cropType, 'Crop updated successfully', 201);
            }

            $cropType = LedgerAccount::create([
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
    public function listLedgerAccounts(){
        $ledgerAccounts = LedgerAccount::where([
            ['id','>',0]
        ])->select('uuid','name','slug','description','type')->get();

        return $this->successResponse($ledgerAccounts, 'All ledger accounts fetched successfully', 201);;
    }

    /**
     * delete transactioncategory
     */
    public function destroyTransactionCategory($transactioncategory_id)
    {
        $transactioncategory = LedgerAccount::findOrFail($transactioncategory_id);
        $transactioncategory->delete();
        return redirect()->back()->with('notice',['type'=>'success','message'=>'TransactionCategory deleted successfully']);
    }

}
