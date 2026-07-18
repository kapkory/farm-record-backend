<?php

namespace Database\Seeders;

use App\Models\Core\Crop;
use App\Models\Core\CropVariety;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a small set of default crops (with common varieties) that most
 * smallholder farmers grow. Crops are global lookup data (no farmer_id),
 * mirroring the animal types seeder.
 */
class CropsSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $crops = [
        [
            'name' => 'Maize',
            'type' => 'annual',
            'description' => 'Staple cereal crop grown for grain.',
            'varieties' => [
                ['name' => 'H614', 'maturity_days' => 150, 'harvest_type' => 'single', 'description' => 'High-altitude hybrid, high yielding.'],
                ['name' => 'H513', 'maturity_days' => 120, 'harvest_type' => 'single', 'description' => 'Medium-altitude hybrid.'],
                ['name' => 'DK8031', 'maturity_days' => 120, 'harvest_type' => 'single', 'description' => 'Drought-tolerant hybrid.'],
            ],
        ],
        [
            'name' => 'Coffee',
            'type' => 'perennial',
            'description' => 'Perennial cash crop; berries picked over several years.',
            'varieties' => [
                ['name' => 'SL28', 'maturity_days' => 1095, 'harvest_type' => 'multiple', 'description' => 'Arabica; first berries around year 3.'],
                ['name' => 'Ruiru 11', 'maturity_days' => 730, 'harvest_type' => 'multiple', 'description' => 'Disease-resistant; first berries around year 2.'],
                ['name' => 'Batian', 'maturity_days' => 730, 'harvest_type' => 'multiple', 'description' => 'Disease-resistant, tall variety.'],
            ],
        ],
    ];

    public function run(): void
    {
        $this->command?->info('🌱 Seeding default crops...');

        foreach ($this->crops as $data) {
            $crop = Crop::firstOrNew(['name' => $data['name']]);
            $crop->fill([
                'uuid' => $crop->uuid ?: (string) Str::orderedUuid(),
                'type' => $data['type'],
                'description' => $data['description'],
                'status' => 1,
            ]);
            $crop->save();

            foreach ($data['varieties'] as $variety) {
                $model = CropVariety::firstOrNew([
                    'crop_id' => $crop->id,
                    'name' => $variety['name'],
                ]);
                $model->fill([
                    'uuid' => $model->uuid ?: (string) Str::orderedUuid(),
                    'maturity_days' => $variety['maturity_days'],
                    'harvest_type' => $variety['harvest_type'],
                    'description' => $variety['description'],
                    'status' => 1,
                ]);
                $model->save();
            }
        }

        $this->command?->info('✅ Default crops seeded successfully.');
    }
}
