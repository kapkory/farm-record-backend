<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $fillable = [
        'uuid',
        'sale_id',
        'user_id',
        'date',
        'amount',
        'payment_method',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
