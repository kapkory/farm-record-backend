<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnimalGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'farm_id',
        'farmer_id',
        'field_id',
        'animal_type_id',
        'animal_breed_id',
        'treatment_plan_id',
        'name',
        'initial_count',
        'current_count',
        'acquired_date',
        'acquisition_type',
        'purpose',
        'description',
        'user_id',
        'status',
    ];

    protected $casts = [
        'acquired_date' => 'date',
        'initial_count' => 'integer',
        'current_count' => 'integer',
        'status' => 'integer',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function animalType(): BelongsTo
    {
        return $this->belongsTo(AnimalType::class);
    }

    public function animalBreed(): BelongsTo
    {
        return $this->belongsTo(AnimalBreed::class);
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    public function hives(): HasMany
    {
        return $this->hasMany(Hive::class);
    }

    public function apiaryProfile(): HasOne
    {
        return $this->hasOne(ApiaryProfile::class);
    }

    public function treatments(): MorphMany
    {
        return $this->morphMany(Treatment::class, 'treatmentable');
    }

    public function productions(): MorphMany
    {
        return $this->morphMany(Production::class, 'productionable');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(AnimalEvent::class, 'eventable');
    }
}
