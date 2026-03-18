<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    protected $fillable = [
        'uuid',
        'ledger_transaction_id',
        'ledger_account_id',
        'type',
        'amount',
        'unit_price',
        'quantity',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
