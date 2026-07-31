<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'interval',
        'trial_days',
        'max_farms',
        'max_animals',
        'max_users',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'trial_days' => 'integer',
        'max_farms' => 'integer',
        'max_animals' => 'integer',
        'max_users' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Days added to the paid-through date when a payment for this plan lands. */
    public function periodDays(): int
    {
        return $this->interval === 'yearly' ? 365 : 30;
    }
}
