<?php

namespace Database\Seeders;

use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AnimalTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Cattle', 'category' => 'livestock', 'tracking_mode' => 'both', 'count_label' => 'heads'],
            ['name' => 'Goats', 'category' => 'livestock', 'tracking_mode' => 'both', 'count_label' => 'animals'],
            ['name' => 'Sheep', 'category' => 'livestock', 'tracking_mode' => 'both', 'count_label' => 'animals'],
            ['name' => 'Pigs', 'category' => 'livestock', 'tracking_mode' => 'both', 'count_label' => 'animals'],
            ['name' => 'Poultry', 'category' => 'poultry', 'tracking_mode' => 'group_only', 'count_label' => 'birds'],
            ['name' => 'Bees', 'category' => 'apiculture', 'tracking_mode' => 'group_only', 'count_label' => 'hives'],
            ['name' => 'Rabbits', 'category' => 'livestock', 'tracking_mode' => 'group_only', 'count_label' => 'animals'],
            ['name' => 'Fish', 'category' => 'aquaculture', 'tracking_mode' => 'group_only', 'count_label' => 'ponds'],
            ['name' => 'Donkeys', 'category' => 'livestock', 'tracking_mode' => 'both', 'count_label' => 'animals'],
            ['name' => 'Horses', 'category' => 'livestock', 'tracking_mode' => 'individual_only', 'count_label' => 'animals'],
            ['name' => 'Camels', 'category' => 'livestock', 'tracking_mode' => 'individual_only', 'count_label' => 'animals'],
            ['name' => 'Dogs', 'category' => 'livestock', 'tracking_mode' => 'individual_only', 'count_label' => 'animals'],
        ];

        foreach ($types as $type) {
            $animalType = AnimalType::firstOrNew(['name' => $type['name']]);
            $animalType->fill([
                'uuid' => $animalType->uuid ?: (string) Str::orderedUuid(),
                'category' => $type['category'],
                'tracking_mode' => $type['tracking_mode'],
                'count_label' => $type['count_label'],
                'status' => 1,
            ]);
            $animalType->save();
        }

        $breeds = [
            'Cattle' => [
                ['name' => 'Holstein', 'purpose' => 'dairy', 'gestation_days' => 283],
                ['name' => 'Friesian', 'purpose' => 'dairy', 'gestation_days' => 283]
            ],
            'Goats' => [
                ['name' => 'Boer', 'purpose' => 'meat', 'gestation_days' => 150],
                ['name' => 'Toggenburg', 'purpose' => 'dairy', 'gestation_days' => 150]
            ],
            'Sheep' => [
                ['name' => 'Merino', 'purpose' => 'wool', 'gestation_days' => 147],
                ['name' => 'Dorper', 'purpose' => 'meat', 'gestation_days' => 147]
            ],
            'Pigs' => [
                ['name' => 'Large White', 'purpose' => 'meat', 'gestation_days' => 114]
            ],
            'Poultry' => [
                ['name' => 'Kienyeji', 'purpose' => 'eggs', 'gestation_days' => 21],
                ['name' => 'Broiler', 'purpose' => 'meat', 'gestation_days' => 21]
            ],
            'Bees' => [
                ['name' => 'Apis mellifera', 'purpose' => 'honey', 'gestation_days' => 21]
            ],
            'Fish' => [
                ['name' => 'Tilapia', 'purpose' => 'meat', 'gestation_days' => 4]
            ],
        ];

        foreach ($breeds as $typeName => $items) {
            $animalType = AnimalType::where('name', $typeName)->first();
            if (! $animalType) {
                continue;
            }

            foreach ($items as $item) {
                $breed = AnimalBreed::firstOrNew([
                    'animal_type_id' => $animalType->id,
                    'name' => $item['name'],
                ]);

                $breed->fill([
                    'uuid' => $breed->uuid ?: (string) Str::orderedUuid(),
                    'purpose' => $item['purpose'],
                    'gestation_days' => $item['gestation_days'],
                    'status' => 1,
                ]);

                $breed->save();
            }
        }
    }
}

