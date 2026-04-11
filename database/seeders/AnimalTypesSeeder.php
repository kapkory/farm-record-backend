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
            'Cattle' => [['name' => 'Holstein', 'purpose' => 'dairy'], ['name' => 'Friesian', 'purpose' => 'dairy']],
            'Goats' => [['name' => 'Boer', 'purpose' => 'meat'], ['name' => 'Toggenburg', 'purpose' => 'dairy']],
            'Sheep' => [['name' => 'Merino', 'purpose' => 'wool'],['name' => 'Dorper', 'purpose' => 'meat']],
            'Pigs' => [['name' => 'Large White', 'purpose' => 'meat']],
            'Poultry' => [['name' => 'Kienyeji', 'purpose' => 'eggs'], ['name' => 'Broiler', 'purpose' => 'meat']],
            'Bees' => [['name' => 'Apis mellifera', 'purpose' => 'honey']],
            'Fish' => [['name' => 'Tilapia', 'purpose' => 'meat']],
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
                    'status' => 1,
                ]);

                $breed->save();
            }
        }
    }
}

