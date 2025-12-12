<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Farm extends Model
{
    use HasFactory;

    protected $table = 'farms';

    protected $fillable = [
        'uuid',
        'name',
        'location',
        'size',
        'size_unit',
        'established_date',
        'description',
        'type',
        'ownership_type',
        'status',
        'farmer_id',
    ];

    protected $casts = [
        'established_date' => 'date',
        'size' => 'double',
    ];

    /**
     * The user who owns / created the farm
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

