<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class CropVariety extends Model
 {
	protected $fillable = ["uuid","crop_id","name","maturity_days","expected_yield","description","harvest_type","status"];

    //
}
