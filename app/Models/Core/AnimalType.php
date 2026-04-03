<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnimalType extends Model
{
    use SoftDeletes;

    public const TRACKING_GROUP_ONLY = 'group_only';
    public const TRACKING_INDIVIDUAL_ONLY = 'individual_only';
    public const TRACKING_BOTH = 'both';

    protected $fillable = [
        'uuid',
        'name',
        'category',
        'tracking_mode',
        'count_label',
        'description',
        'status',
    ];

    public function breeds(): HasMany
    {
        return $this->hasMany(AnimalBreed::class);
    }

    public function allowsGroups(): bool
    {
        return $this->tracking_mode !== self::TRACKING_INDIVIDUAL_ONLY;
    }

    public function allowsIndividuals(): bool
    {
        return $this->tracking_mode !== self::TRACKING_GROUP_ONLY;
    }

    public function isGroupOnly(): bool
    {
        return $this->tracking_mode === self::TRACKING_GROUP_ONLY;
    }
}

