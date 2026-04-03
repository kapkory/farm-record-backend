<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnimalBreed extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'animal_type_id',
        'name',
        'purpose',
        'average_lifespan_months',
        'gestation_days',
        'description',
        'status',
    ];

    public function animalType(): BelongsTo
    {
        return $this->belongsTo(AnimalType::class);
    }
}

