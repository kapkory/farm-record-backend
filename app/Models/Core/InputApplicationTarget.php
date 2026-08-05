<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One animal or group covered by an application, and its share of the cost.
 *
 * This is what makes a shared purchase visible on an individual animal: the
 * ledger holds one posting against the input, and these rows say who benefited
 * from it and by how much.
 */
class InputApplicationTarget extends Model
{
    protected $fillable = [
        'uuid',
        'input_application_id',
        'targetable_type',
        'targetable_id',
        'head_count',
        'basis_value',
        'allocated_cost',
        'treatment_id',
    ];

    protected $casts = [
        'head_count' => 'integer',
        'basis_value' => 'float',
        'allocated_cost' => 'float',
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

    public function application(): BelongsTo
    {
        return $this->belongsTo(InputApplication::class, 'input_application_id');
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
