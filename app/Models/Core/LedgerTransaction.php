<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class LedgerTransaction extends Model
 {
	protected $fillable = ["farm_id","date","description","payment_method","reference_number","farmer_id"];

    //
}
