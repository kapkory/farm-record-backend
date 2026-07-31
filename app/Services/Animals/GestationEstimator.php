<?php

namespace App\Services\Animals;

use App\Models\Core\Animal;

/**
 * Works out how many days from service to expected birth for a dam.
 *
 * Prefers the breed's configured gestation period; when that is not set
 * (common — the field is optional) it falls back to a built-in table of
 * typical gestation lengths keyed off the animal type name, so a farmer
 * always gets an expected birth date without having to configure breeds.
 * The dam's per-animal `gestation_adjustment_days` is then applied.
 */
class GestationEstimator
{
    /**
     * Typical gestation / incubation lengths in days, by species keyword.
     * Order matters: specific species are matched before generic keywords
     * like "dairy"/"beef", so "Dairy Goat" resolves to goat, not cattle.
     */
    public const SPECIES_DAYS = [
        'goat' => 150,
        'sheep' => 152,
        'pig' => 114,
        'swine' => 114,
        'rabbit' => 31,
        'buffalo' => 310,
        'camel' => 390,
        'horse' => 340,
        'donkey' => 365,
        'chicken' => 21,
        'duck' => 28,
        'turkey' => 28,
        'goose' => 30,
        'quail' => 18,
        'poultry' => 21,
        'dog' => 63,
        'cat' => 65,
        'cattle' => 283,
        'cow' => 283,
        'heifer' => 283,
        'bull' => 283,
        // Generic descriptors last — only reached when no species matched.
        'dairy' => 283,
        'beef' => 283,
    ];

    /** Effective gestation days for a dam, or null when nothing is known. */
    public function daysFor(Animal $dam): ?int
    {
        $base = $dam->relationLoaded('animalBreed')
            ? $dam->animalBreed?->gestation_days
            : $dam->breed?->gestation_days;

        $base ??= $this->fallbackDaysForType($dam);

        if ($base === null) {
            return null;
        }

        return max(1, (int) $base + (int) ($dam->gestation_adjustment_days ?? 0));
    }

    protected function fallbackDaysForType(Animal $dam): ?int
    {
        $typeName = strtolower(
            $dam->relationLoaded('animalType')
                ? (string) $dam->animalType?->name
                : (string) optional($dam->animalType()->first())->name
        );

        if ($typeName === '') {
            return null;
        }

        foreach (self::SPECIES_DAYS as $keyword => $days) {
            if (str_contains($typeName, $keyword)) {
                return $days;
            }
        }

        return null;
    }
}
