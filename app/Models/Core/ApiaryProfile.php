<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiaryProfile extends Model
{
    public const SCHEME_ALPHA = 'alpha';

    public const SCHEME_NUMERIC = 'numeric';

    public const SCHEMES = [self::SCHEME_ALPHA, self::SCHEME_NUMERIC];

    protected $fillable = [
        'animal_group_id',
        'naming_prefix',
        'naming_scheme',
        'next_sequence',
        'default_harvest_interval_days',
    ];

    // Mirrors the column defaults so firstOrCreate() models are usable
    // without a refresh round-trip.
    protected $attributes = [
        'naming_scheme' => self::SCHEME_ALPHA,
        'next_sequence' => 1,
        'default_harvest_interval_days' => 90,
    ];

    protected $casts = [
        'next_sequence' => 'integer',
        'default_harvest_interval_days' => 'integer',
    ];

    public function apiary(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }
}
