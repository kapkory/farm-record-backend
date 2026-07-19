<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_OWED = 'owed';

    public const STATUS_VOID = 'void';

    /** Item category → global income account name (see LedgerAccountsSeeder). */
    public const CATEGORY_INCOME_ACCOUNTS = [
        'animal' => 'Livestock Sales',
        'crop' => 'Crop Sales',
        'animal_product' => 'Milk, Eggs & Honey Sales',
        'bee_product' => 'Milk, Eggs & Honey Sales',
        'other' => 'Other Income',
    ];

    protected $fillable = [
        'uuid',
        'farm_id',
        'farmer_id',
        'user_id',
        'buyer_id',
        'date',
        'payment_method',
        'amount_total',
        'amount_paid',
        'status',
        'notes',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_total' => 'float',
        'amount_paid' => 'float',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    /**
     * All money movements attached to this sale (income posting, payments,
     * reversals). Not a morphMany: the ledger service stores the FQCN in
     * transactionable_type (the codebase-wide pattern) while the morph map
     * would make a morphMany query the 'sale' alias.
     */
    public function ledgerTransactions(): Builder
    {
        return LedgerTransaction::query()
            ->where('transactionable_id', $this->id)
            ->whereIn('transactionable_type', ['sale', self::class]);
    }

    public function balanceDue(): float
    {
        return max(0, round($this->amount_total - $this->amount_paid, 2));
    }
}
