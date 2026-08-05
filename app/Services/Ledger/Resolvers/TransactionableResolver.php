<?php

namespace App\Services\Ledger\Resolvers;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\FarmInput;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use App\Models\Core\Sale;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class TransactionableResolver
{
    public function resolve(string $transactionFor, string $uuid): Model
    {
        return match ($transactionFor) {
            // The whole farm — for costs that aren't tied to one record, like
            // salaries, rent, or a dip day covering every animal.
            'farm' => Farm::where('uuid', $uuid)->firstOrFail(),
            'planting' => Planting::where('uuid', $uuid)->firstOrFail(),
            'animal_group' => AnimalGroup::where('uuid', $uuid)->firstOrFail(),
            'animal' => Animal::where('uuid', $uuid)->firstOrFail(),
            'hive' => Hive::where('uuid', $uuid)->firstOrFail(),
            'sale' => Sale::where('uuid', $uuid)->firstOrFail(),
            // A bulk input the whole farm draws on — the purchase posts against
            // the input itself rather than an arbitrary animal.
            'farm_input' => FarmInput::where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException("Unsupported transaction target [{$transactionFor}]."),
        };
    }
}

