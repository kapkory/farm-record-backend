<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SaleItem extends Model
{
    protected $fillable = [
        'uuid',
        'sale_id',
        'sellable_type',
        'sellable_id',
        'category',
        'product',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
        'production_id',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function sellable(): MorphTo
    {
        return $this->morphTo();
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }
}
