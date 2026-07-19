<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Buyer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'farmer_id',
        'user_id',
        'name',
        'phone',
        'notes',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
