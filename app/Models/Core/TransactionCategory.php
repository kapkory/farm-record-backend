<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class TransactionCategory extends Model
 {

	protected $fillable = ["uuid","name","slug","type","parent_id","description","is_system","status"];

    //
}
