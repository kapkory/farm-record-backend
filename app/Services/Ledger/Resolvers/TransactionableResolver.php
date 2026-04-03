<?php

namespace App\Services\Ledger\Resolvers;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Planting;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TransactionableResolver
{
    public function resolve(string $transactionFor, string $uuid): Model
    {
        return match ($transactionFor) {
            'planting' => Planting::where('uuid', $uuid)->firstOrFail(),
            'animal_group' => AnimalGroup::where('uuid', $uuid)->firstOrFail(),
            'animal' => Animal::where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException("Unsupported transaction target [{$transactionFor}]."),
        };
    }
}

