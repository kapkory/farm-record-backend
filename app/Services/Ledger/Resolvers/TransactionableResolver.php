<?php

namespace App\Services\Ledger\Resolvers;

use App\Models\Core\Planting;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TransactionableResolver
{
    public function resolve(string $transactionFor, string $uuid): Model
    {
        return match ($transactionFor) {
            'planting' => Planting::where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException("Unsupported transaction target [{$transactionFor}]."),
        };
    }
}

