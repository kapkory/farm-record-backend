<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'uuid',
        'farmer_id',
        'plan_id',
        'status',
        'started_at',
        'trial_ends_at',
        'current_period_end',
        'canceled_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * The status the subscription *should* be in right now, given the clock.
     * A trialing/active subscription whose paid-through date has passed lapses
     * to past_due; a canceled one whose period has ended becomes expired.
     */
    public function effectiveStatus(): string
    {
        if (in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_CANCELED], true)) {
            return $this->status;
        }

        $now = now();

        if ($this->status === self::STATUS_TRIALING) {
            if ($this->trial_ends_at && $this->trial_ends_at->isPast()) {
                return self::STATUS_PAST_DUE;
            }

            return self::STATUS_TRIALING;
        }

        // active
        if ($this->current_period_end && $this->current_period_end->isPast()) {
            return self::STATUS_PAST_DUE;
        }

        return self::STATUS_ACTIVE;
    }

    public function daysRemaining(): ?int
    {
        $end = $this->status === self::STATUS_TRIALING ? $this->trial_ends_at : $this->current_period_end;

        return $end ? (int) ceil(now()->floatDiffInDays($end, false)) : null;
    }
}
