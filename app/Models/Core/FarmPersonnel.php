<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FarmPersonnel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'role',
        'phone',
        'email',
        'notes',
        'farmer_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
