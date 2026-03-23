<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Planting extends Model
 {
	protected $fillable = ["farm_id", 'uuid',"field_id","crop_id","crop_variety_id","date_planted",
        "expected_harvest_date","actual_harvest_date","quantity_planted","purpose","user_id","description"];

    public function farm(){
        return $this->belongsTo(Farm::class,'farm_id');
    }
    public function crop(){
        return $this->belongsTo(Crop::class,'crop_id');
    }
    public function field(){
        return $this->belongsTo(Field::class,'field_id');
    }
    public function cropVariety(){
        return $this->belongsTo(CropVariety::class,'crop_variety_id');
    }
    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable');
    }
    public function productions(): MorphMany
    {
        return $this->morphMany(Production::class, 'productionable');
    }
    public function treatments(): MorphMany
    {
        return $this->morphMany(Treatment::class, 'treatmentable');
    }
}
