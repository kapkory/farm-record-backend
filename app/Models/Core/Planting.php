<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Planting extends Model
 {
	protected $fillable = ["farm_id", 'uuid',"field_id","crop_id","crop_variety_id","date_planted",
        "expected_harvest_date","actual_harvest_date","quantity_planted","purpose","user_id","description"];

    //
}
