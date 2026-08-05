<?php

namespace App\Services\Animals;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * How many days should pass between weighings for a given animal or group.
 *
 * Same shape as GestationEstimator: an explicitly configured value wins, and a
 * built-in table covers everyone else so a farmer gets a sensible weighing
 * rhythm without setting anything up first.
 *
 * Order of preference:
 *   1. the animal's / group's own `weighing_interval_days`
 *   2. its animal type's `weighing_interval_days` (a settings screen field)
 *   3. a default for the type's category
 *   4. 30 days
 */
class WeighingIntervalResolver
{
    /** Typical weighing rhythm in days, by animal type category. */
    public const CATEGORY_DAYS = [
        // Broilers put on weight fast enough that a fortnight already hides
        // a problem; weekly sampling is the norm.
        'poultry' => 7,
        'aquaculture' => 14,
        'livestock' => 30,
        'apiculture' => 30,
    ];

    public const DEFAULT_DAYS = 30;

    /** @param  Animal|AnimalGroup|Model  $subject */
    public function daysFor(Model $subject): int
    {
        $own = $subject->weighing_interval_days ?? null;

        if ($own) {
            return max(1, (int) $own);
        }

        $type = $subject->relationLoaded('animalType')
            ? $subject->animalType
            : $subject->animalType()->first();

        if ($type?->weighing_interval_days) {
            return max(1, (int) $type->weighing_interval_days);
        }

        return self::CATEGORY_DAYS[$type?->category] ?? self::DEFAULT_DAYS;
    }
}
