<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Something the farm bought in bulk to use across many animals — a tin of dip,
 * a bag of feed, a vial of vaccine.
 *
 * The whole cost is posted to the ledger once, when the money left. Each
 * application then draws stock down and attributes a share of that already-paid
 * cost to the animals it covered; applications never post to the ledger
 * themselves, or the books would count the same shilling twice.
 */
class FarmInput extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['dip', 'drug', 'vaccine', 'feed', 'fertilizer', 'seed', 'other'];

    protected $fillable = [
        'uuid',
        'farm_id',
        'farmer_id',
        'name',
        'category',
        'treatment_type_id',
        'quantity',
        'unit',
        'quantity_remaining',
        'total_cost',
        'unit_cost',
        'purchase_date',
        'supplier',
        'notes',
        'ledger_transaction_id',
        'user_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'quantity' => 'float',
        'quantity_remaining' => 'float',
        'total_cost' => 'float',
        'unit_cost' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::orderedUuid();
            }
        });
    }

    /** Cost per unit, frozen at purchase so past applications stay priced as charged. */
    public static function unitCostFor(float $totalCost, float $quantity): float
    {
        return $quantity > 0 ? round($totalCost / $quantity, 4) : 0.0;
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(InputApplication::class);
    }

    /** How much of the purchase has actually been used. */
    public function getQuantityUsedAttribute(): float
    {
        return round((float) $this->quantity - (float) $this->quantity_remaining, 3);
    }

    public function getIsDepletedAttribute(): bool
    {
        return (float) $this->quantity_remaining <= 0;
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0);
    }
}
