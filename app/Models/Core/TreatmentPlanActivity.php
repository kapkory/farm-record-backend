<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlanActivity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'treatment_plan_id',
        'treatment_type_id',
        'vaccine',
        'disease',
        'route',
        'age_days',
        'priority',
        'notes',
        'user_id',
    ];

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class);
    }
}
