<?php

namespace Database\Seeders;

use App\Models\Core\TreatmentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the poultry vaccines used by the default Layers Chicken Vaccination
 * Schedule as global livestock treatment types. Treatment types are global
 * lookup data (no farmer scoping).
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

    public function run(): void
    {
        $this->command?->info('💉 Seeding poultry treatment types...');

        foreach ($this->types as $type) {
            $model = TreatmentType::firstOrNew(['name' => $type['name']]);
            $model->fill([
                'uuid' => $model->uuid ?: (string) Str::orderedUuid(),
                'description' => $type['description'],
                'type' => 'livestock',
                'status' => 1,
            ]);
            $model->save();
        }

        $this->command?->info('✅ Poultry treatment types seeded successfully.');
    }
}
