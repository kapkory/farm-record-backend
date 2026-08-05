<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
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
        'dam_id',
        'sire_id',
        'animal_breeding_id',
        'treatment_plan_id',
        'tag_number',
        'name',
        'gender',
        'date_of_birth',
        'acquisition_date',
        'acquisition_type',
        'purchase_price',
        'gestation_adjustment_days',
        'weighing_interval_days',
        'status',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'acquisition_date' => 'date',
        'purchase_price' => 'decimal:2',
        'gestation_adjustment_days' => 'integer',
        'weighing_interval_days' => 'integer'];

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

    /** Shares of bulk-purchased inputs (dip, feed, drugs) charged to this record. */
    public function inputAllocations(): MorphMany
    {
        return $this->morphMany(InputApplicationTarget::class, 'targetable');
    }

    /** Live weight readings, oldest first when ordered. */
    public function weights(): MorphMany
    {
        return $this->morphMany(AnimalWeight::class, 'weighable');
    }

    /** The most recent reading, for list views and the animal header. */
    public function latestWeight(): MorphOne
    {
        return $this->morphOne(AnimalWeight::class, 'weighable')->latestOfMany('measured_on');
    }

    public function ledgerTransactions(): MorphMany
    {
        return $this->morphMany(LedgerTransaction::class, 'transactionable');
    }

    public function productions(): MorphMany
    {
        return $this->morphMany(Production::class, 'productionable');
    }

    /**
     * Breeding records where this animal is the dam (mother)
     */
    public function breedingsAsDam(): HasMany
    {
        return $this->hasMany(AnimalBreeding::class, 'dam_id');
    }

    /**
     * Breeding records where this animal is the sire (father)
     */
    public function breedingsAsSire(): HasMany
    {
        return $this->hasMany(AnimalBreeding::class, 'sire_id');
    }

    /**
     * All breeding records for this animal (as either dam or sire)
     */
    public function allBreedings()
    {
        return AnimalBreeding::where('dam_id', $this->id)
            ->orWhere('sire_id', $this->id);
    }

    /**
     * Get the breed relationship (alias for animalBreed for easier access)
     */
    public function breed(): BelongsTo
    {
        return $this->animalBreed();
    }

    /** The pregnancy this animal was born from, when it was recorded here. */
    public function birthBreeding(): BelongsTo
    {
        return $this->belongsTo(AnimalBreeding::class, 'animal_breeding_id');
    }

    /** The animal's recorded mother, if known. */
    public function damParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dam_id');
    }

    /** The animal's recorded father, if known. */
    public function sireParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sire_id');
    }

    /**
     * Generate the next sequential tag number in the format FC-000001.
     */
    public static function generateTagNumber(): string
    {
        $latest = static::withTrashed()
            ->where('tag_number', 'LIKE', 'FC-%')
            ->orderByRaw('CAST(SUBSTRING(tag_number, 4) AS UNSIGNED) DESC')
            ->value('tag_number');

        $next = $latest ? ((int) ltrim(substr($latest, 3), '0') ?: 0) + 1 : 1;

        return 'FC-'.str_pad($next, 6, '0', STR_PAD_LEFT);
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
