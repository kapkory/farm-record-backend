<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnimalEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'eventable_type',
        'eventable_id',
        'event_type',
        'date',
        'quantity',
        'description',
        'metadata',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
        'quantity' => 'integer',
    ];

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }
}

