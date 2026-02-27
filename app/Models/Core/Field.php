<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Field extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'farm_id',
        'name',
        'size',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'size' => 'decimal:2',
    ];

    /**
     * Get the farm that owns the field.
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
}
