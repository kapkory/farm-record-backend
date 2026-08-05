<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One live-weight reading for an animal or a sampled group.
 *
 * `weight_kg` is the canonical figure — kilograms, per head — so readings from
 * an individual ox and from a ten-bird broiler sample can be compared and
 * trended without converting anything first. `entered_value`/`entered_unit`
 * preserve what the farmer typed so the UI can echo "850 g" back rather than
 * "0.85 kg".
 */
class AnimalWeight extends Model
{
    use SoftDeletes;

    public const UNITS = ['kg', 'g', 'lb'];

    /** Multipliers onto kilograms. */
    public const UNIT_TO_KG = [
        'kg' => 1.0,
        'g' => 0.001,
        'lb' => 0.45359237,
    ];

    protected $fillable = [
        'uuid',
        'weighable_type',
        'weighable_id',
        'measured_on',
        'weight_kg',
        'entered_value',
        'entered_unit',
        'sample_size',
        'sample_total_kg',
        'next_task_id',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'measured_on' => 'date',
        'weight_kg' => 'float',
        'entered_value' => 'float',
        'sample_total_kg' => 'float',
        'sample_size' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::orderedUuid();
            }
        });
    }

    /** Convert a farmer-entered figure to canonical kilograms. */
    public static function toKilograms(float $value, string $unit): float
    {
        return round($value * (self::UNIT_TO_KG[$unit] ?? 1.0), 3);
    }

    public function weighable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The weighing task this reading scheduled for next time. */
    public function nextTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'next_task_id');
    }

    /** True when this reading averages a sample rather than weighing one head. */
    public function getIsSampleAttribute(): bool
    {
        return $this->sample_size > 1;
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('measured_on')->orderBy('id');
    }
}
