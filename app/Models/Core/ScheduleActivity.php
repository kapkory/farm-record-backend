<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduleActivity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'schedule_id',
        'activity_name',
        'days_since_planting',
        'priority',
        'notes',
        'user_id',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
