<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Treatment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'treatment_type_id',
        'farm_id',
        'details',
        'treatmentable_type',
        'treatmentable_id',
        'date',
        'notes',
        'retreat_date',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'retreat_date' => 'date',
    ];

    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class, 'treatment_type_id');
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    public function treatmentable(): MorphTo
    {
        return $this->morphTo();
    }
}
