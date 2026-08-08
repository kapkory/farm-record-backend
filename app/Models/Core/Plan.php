<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    /** The plan every new farmer starts on while the product is in testing. */
    public const DEFAULT_SLUG = 'free-trial';

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

    /**
     * The plan to put a new farmer on when they don't choose one. Falls back
     * to the cheapest active plan so registration never silently ends up with
     * no subscription if the default is ever renamed or deactivated.
     */
    public static function default(): ?self
    {
        return static::query()->where('slug', self::DEFAULT_SLUG)->where('is_active', true)->first()
            ?? static::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->first();
    }

    /** Days added to the paid-through date when a payment for this plan lands. */
    public function periodDays(): int
    {
        return $this->interval === 'yearly' ? 365 : 30;
    }
}
