<?php

namespace Database\Seeders;

use App\Models\Core\TreatmentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds default treatment types as global lookup data (no farmer scoping):
 * the poultry vaccines used by the default Layers Chicken Vaccination
 * Schedule (livestock), plus common crop treatments (crop) so a new farm
 * has something to pick from before an admin adds their own.
 */
class TreatmentTypesSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, description: string}>
     */
    protected array $types = [
        ['name' => "Marek's Vaccine", 'description' => "Protects against Marek's disease. Given day-old."],
        ['name' => 'Gumboro Vaccine (IBD)', 'description' => 'Protects against Infectious Bursal Disease (Gumboro).'],
        ['name' => 'Newcastle Vaccine (Lasota)', 'description' => 'Live Lasota strain against Newcastle disease.'],
        ['name' => 'Newcastle Vaccine (Komarov)', 'description' => 'Killed Komarov strain against Newcastle disease; injectable.'],
        ['name' => 'Fowl Pox Vaccine', 'description' => 'Protects against Fowl Pox. Given by wing web stab.'],
        ['name' => 'Fowl Typhoid Vaccine', 'description' => 'Protects against Fowl Typhoid.'],
        ['name' => 'Fowl Cholera Vaccine', 'description' => 'Protects against Fowl Cholera.'],
        ['name' => 'Egg Drop Syndrome Vaccine (EDS)', 'description' => 'Protects laying hens against Egg Drop Syndrome.'],
    ];

    /**
     * @var array<int, array{name: string, description: string}>
     */
    protected array $cropTypes = [
        ['name' => 'Fertilizer Application', 'description' => 'Applying basal, top-dressing, or foliar fertiliser to boost crop growth.'],
        ['name' => 'Pesticide Spray', 'description' => 'Spraying to control insect pests and other crop pests.'],
        ['name' => 'Herbicide Application', 'description' => 'Spraying to control weeds in the field.'],
        ['name' => 'Fungicide Treatment', 'description' => 'Spraying to prevent or control fungal diseases.'],
        ['name' => 'Irrigation', 'description' => 'Watering the crop outside of natural rainfall.'],
        ['name' => 'Weeding', 'description' => 'Manual or mechanical removal of weeds.'],
        ['name' => 'Pruning', 'description' => 'Cutting back plant growth to improve yield or plant health.'],
        ['name' => 'Soil Treatment', 'description' => 'Liming, soil conditioning, or other soil amendments.'],
        ['name' => 'Pest Scouting', 'description' => 'Field inspection to check for pest or disease pressure.'],
        ['name' => 'Seed Treatment', 'description' => 'Treating seed before planting, e.g. fungicide or insecticide dressing.'],
    ];

    public function run(): void
    {
        $this->command?->info('💉 Seeding poultry treatment types...');
        $this->seedTypes($this->types, 'livestock');
        $this->command?->info('✅ Poultry treatment types seeded successfully.');

        $this->command?->info('🌾 Seeding crop treatment types...');
        $this->seedTypes($this->cropTypes, 'crop');
        $this->command?->info('✅ Crop treatment types seeded successfully.');
    }

    /**
     * @param  array<int, array{name: string, description: string}>  $types
     */
    protected function seedTypes(array $types, string $category): void
    {
        foreach ($types as $type) {
            $model = TreatmentType::firstOrNew(['name' => $type['name']]);
            $model->fill([
                'uuid' => $model->uuid ?: (string) Str::orderedUuid(),
                'description' => $type['description'],
                'type' => $category,
                'status' => 1,
            ]);
            $model->save();
        }
    }
}
