<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'productionable_type',
        'productionable_id',
        'name',
        'quantity',
        'date',
        'unit',
        'user_id',
        'trace_number',
        'grade',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'float',
    ];

    public function productionable(): MorphTo
    {
        return $this->morphTo();
    }
}
