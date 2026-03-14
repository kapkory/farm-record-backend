<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
 {

	protected $fillable = ["uuid","name","slug","type","parent_id","description","user_id","is_system","status"];

    //
}
