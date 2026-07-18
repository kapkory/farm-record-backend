<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'crop_id',
        'farmer_id',
        'is_system',
        'status',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function activities(): HasMany
    {
        return $this->hasMany(ScheduleActivity::class)->orderBy('days_since_planting');
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }
}
