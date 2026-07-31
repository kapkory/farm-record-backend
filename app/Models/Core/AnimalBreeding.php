<?php

namespace App\Models\Core;

use App\Models\User;
use App\Services\Animals\GestationEstimator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AnimalBreeding extends Model
{
    protected $fillable = [
        'uuid',
        'farm_id',
        'dam_id',
        'sire_id',
        'sire_type',
        'service_date',
        'expected_birth_date',
        'status',
        'birth_event_id',
        'ai_straw_code',
        'ai_bull_name',
        'ai_technician',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'service_date' => 'date',
        'expected_birth_date' => 'date',
    ];

    public const SIRE_TYPES = [
        'natural',
        'ai',
        'embryo',
    ];

    public const STATUSES = [
        'pending',
        'born',
        'aborted',
        'failed',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::orderedUuid();
            }

            // Estimate expected birth date when the farmer didn't set one.
            if ($model->service_date && $model->dam_id && ! $model->expected_birth_date) {
                $model->expected_birth_date = static::estimateBirthDate($model);
            }
        });

        static::updating(function ($model) {
            // Re-estimate when the service date changes and no manual date is set.
            if ($model->isDirty('service_date') && $model->service_date && $model->dam_id) {
                $model->expected_birth_date = static::estimateBirthDate($model)
                    ?? $model->expected_birth_date;
            }
        });
    }

    /** Estimate the birth date for a breeding from its dam's gestation. */
    protected static function estimateBirthDate(self $model): ?Carbon
    {
        $dam = Animal::with(['animalBreed', 'animalType'])->find($model->dam_id);

        if (! $dam) {
            return null;
        }

        $days = app(GestationEstimator::class)->daysFor($dam);

        return $days ? $model->service_date->copy()->addDays($days) : null;
    }

    /**
     * Get the farm that owns this breeding record
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the dam (mother) animal
     */
    public function dam(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'dam_id');
    }

    /**
     * Get the sire (father) animal
     */
    public function sire(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sire_id');
    }

    /**
     * Get the birth event if birth has occurred
     */
    public function birthEvent(): BelongsTo
    {
        return $this->belongsTo(AnimalEvent::class, 'birth_event_id');
    }

    /**
     * Get the user who recorded this breeding
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending breedings
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for successful births
     */
    public function scopeBorn($query)
    {
        return $query->where('status', 'born');
    }

    /**
     * Scope for breedings due soon (within specified days)
     */
    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('status', 'pending')
            ->where('expected_birth_date', '<=', now()->addDays($days))
            ->where('expected_birth_date', '>=', now());
    }

    /**
     * Scope for overdue breedings
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('expected_birth_date', '<', now());
    }

    /**
     * Check if breeding is artificial insemination
     */
    public function isArtificialInsemination(): bool
    {
        return $this->sire_type === 'ai';
    }

    /**
     * Check if breeding is natural
     */
    public function isNaturalBreeding(): bool
    {
        return $this->sire_type === 'natural';
    }

    /**
     * Check if breeding is embryo transfer
     */
    public function isEmbryoTransfer(): bool
    {
        return $this->sire_type === 'embryo';
    }

    /**
     * Get days until expected birth
     */
    public function getDaysUntilBirthAttribute(): ?int
    {
        if (! $this->expected_birth_date || $this->status !== 'pending') {
            return null;
        }

        return now()->diffInDays($this->expected_birth_date, false);
    }

    /**
     * Check if breeding is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' &&
               $this->expected_birth_date &&
               $this->expected_birth_date->isPast();
    }
}
