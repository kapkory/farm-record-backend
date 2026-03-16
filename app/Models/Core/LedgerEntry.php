<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
 {
	protected $fillable = ["ledger_transaction_id","ledger_account_id","type","amount","user_id"];

    //
}
