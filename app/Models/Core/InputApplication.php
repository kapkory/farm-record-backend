<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One use of a farm input — "dipped the herd on 12 August, 30 ml".
 *
 * Carries the cost of the quantity used and the rule by which that cost was
 * split, so an old record still explains itself even after the split rule
 * elsewhere in the app changes.
 */
class InputApplication extends Model
{
    use SoftDeletes;

    public const BASIS_PER_HEAD = 'per_head';
    public const BASIS_BY_WEIGHT = 'by_weight';
    public const BASIS_MANUAL = 'manual';

    public const BASES = [self::BASIS_PER_HEAD, self::BASIS_BY_WEIGHT, self::BASIS_MANUAL];

    protected $fillable = [
        'uuid',
        'farm_input_id',
        'farm_id',
        'date',
        'quantity_used',
        'total_cost',
        'allocation_basis',
        'details',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity_used' => 'float',
        'total_cost' => 'float',
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

    public function farmInput(): BelongsTo
    {
        return $this->belongsTo(FarmInput::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(InputApplicationTarget::class);
    }

    /** Total head covered, across every animal and group treated. */
    public function getHeadCoveredAttribute(): int
    {
        return (int) $this->targets->sum('head_count');
    }
}
