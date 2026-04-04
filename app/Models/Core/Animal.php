<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Animal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'farm_id',
        'farmer_id',
        'animal_group_id',
        'animal_type_id',
        'animal_breed_id',
        'tag_number',
        'name',
        'gender',
        'date_of_birth',
        'acquisition_date',
        'acquisition_type',
        'weight',
        'weight_unit',
        'status',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'acquisition_date' => 'date',
        'weight' => 'decimal:2',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animalGroup(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class);
    }

    public function animalType(): BelongsTo
    {
        return $this->belongsTo(AnimalType::class);
    }

    public function animalBreed(): BelongsTo
    {
        return $this->belongsTo(AnimalBreed::class);
    }

    public function treatments(): MorphMany
    {
        return $this->morphMany(Treatment::class, 'treatmentable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(AnimalEvent::class, 'eventable');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable');
    }

    public function productions(): MorphMany
    {
        return $this->morphMany(Production::class, 'productionable');
    }

    public function scopeStandalone(Builder $query): Builder
    {
        return $query->whereNull('animal_group_id');
    }

    public function scopeGrouped(Builder $query): Builder
    {
        return $query->whereNotNull('animal_group_id');
    }

    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_id', $farmId);
    }

    public function getIsStandaloneAttribute(): bool
    {
        return $this->animal_group_id === null;
    }
}

