<?php

namespace App\Services\Bees;

/**
 * Bee product vocabulary. Stored in productions.name / productions.unit —
 * no schema of its own. Only READINESS_PRODUCTS reset a hive's harvest clock.
 */
final class BeeProduct
{
    /** @var array<string, array{label: string, unit: string}> */
    public const PRODUCTS = [
        'honey' => ['label' => 'Honey', 'unit' => 'kg'],
        'comb_honey' => ['label' => 'Comb honey', 'unit' => 'kg'],
        'beeswax' => ['label' => 'Beeswax', 'unit' => 'kg'],
        'propolis' => ['label' => 'Propolis', 'unit' => 'g'],
        'royal_jelly' => ['label' => 'Royal jelly', 'unit' => 'g'],
        'pollen' => ['label' => 'Pollen', 'unit' => 'g'],
        'bee_venom' => ['label' => 'Bee venom', 'unit' => 'g'],
    ];

    /** Products whose harvest resets last_harvested_at / next_harvest_due. */
    public const READINESS_PRODUCTS = ['honey', 'comb_honey'];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::PRODUCTS);
    }

    public static function defaultUnit(string $product): string
    {
        return self::PRODUCTS[$product]['unit'] ?? 'kg';
    }

    public static function affectsReadiness(string $product): bool
    {
        return in_array($product, self::READINESS_PRODUCTS, true);
    }
}
