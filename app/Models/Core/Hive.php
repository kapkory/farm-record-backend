<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hive extends Model
{
    use SoftDeletes;

    public const OCCUPANCY_OCCUPIED = 'occupied';

    public const OCCUPANCY_EMPTY = 'empty';

    public const OCCUPANCY_ABSCONDED = 'absconded';

    public const OCCUPANCY_DEAD = 'dead';

    public const OCCUPANCIES = [
        self::OCCUPANCY_OCCUPIED,
        self::OCCUPANCY_EMPTY,
        self::OCCUPANCY_ABSCONDED,
        self::OCCUPANCY_DEAD,
    ];

    public const HIVE_TYPES = ['langstroth', 'kenya_top_bar', 'log', 'box'];

    public const DEFAULT_HARVEST_INTERVAL_DAYS = 90;

    protected $fillable = [
        'uuid',
        'farm_id',
        'farmer_id',
        'animal_group_id',
        'sequence',
        'code',
        'name',
        'hive_type',
        'occupancy',
        'installed_date',
        'last_inspected_at',
        'last_harvested_at',
        'next_harvest_due',
        'harvest_interval_days',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'installed_date' => 'date',
        'last_inspected_at' => 'date',
        'last_harvested_at' => 'date',
        'next_harvest_due' => 'date',
        'harvest_interval_days' => 'integer',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function apiary(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }

    public function productions(): MorphMany
    {
        return $this->morphMany(Production::class, 'productionable');
    }

    public function treatments(): MorphMany
    {
        return $this->morphMany(Treatment::class, 'treatmentable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function effectiveHarvestIntervalDays(): int
    {
        return $this->harvest_interval_days
            ?? $this->apiary?->apiaryProfile?->default_harvest_interval_days
            ?? self::DEFAULT_HARVEST_INTERVAL_DAYS;
    }

    /**
     * @return array{status: string, next_harvest_due: ?string, days_remaining: ?int}
     */
    public function harvestReadiness(): array
    {
        $today = now()->startOfDay();

        if ($this->next_harvest_due) {
            $due = $this->next_harvest_due->startOfDay();

            return [
                'status' => $due->lte($today) ? 'ready' : 'waiting',
                'next_harvest_due' => $due->toDateString(),
                'days_remaining' => $due->lte($today) ? 0 : (int) $today->diffInDays($due),
            ];
        }

        // Never harvested: ready once a full interval has passed since installation.
        if ($this->installed_date) {
            $due = $this->installed_date->startOfDay()->addDays($this->effectiveHarvestIntervalDays());

            if ($due->lte($today)) {
                return ['status' => 'ready', 'next_harvest_due' => null, 'days_remaining' => 0];
            }
        }

        return ['status' => 'unknown', 'next_harvest_due' => null, 'days_remaining' => null];
    }
}
