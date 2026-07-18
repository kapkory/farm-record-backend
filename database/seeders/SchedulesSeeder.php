<?php

namespace Database\Seeders;

use App\Models\Core\Crop;
use App\Models\Core\Schedule;
use App\Models\Core\ScheduleActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds default "system" planting schedules for Maize and Coffee.
 *
 * These are global templates (farmer_id = null, is_system = true) so every
 * farmer gets a ready-made activity plan without having to build one. When a
 * farmer starts a planting against one of these schedules, PlantingObserver
 * turns each activity into a dated Task. Depends on CropsSeeder.
 *
 * Priority: 1 = low, 2 = medium, 3 = high (matches Task priority values).
 */
class SchedulesSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $schedules = [
        [
            'crop' => 'Maize',
            'name' => 'Maize – Standard Season',
            'activities' => [
                ['day' => 0,   'priority' => 3, 'title' => 'Land preparation & planting', 'notes' => 'Plough, make furrows and plant. Apply DAP fertilizer in the planting hole.'],
                ['day' => 14,  'priority' => 2, 'title' => 'First weeding', 'notes' => 'Remove weeds early while the crop is young.'],
                ['day' => 21,  'priority' => 3, 'title' => 'Top dressing – first CAN application', 'notes' => 'Apply CAN when the maize is knee-high.'],
                ['day' => 35,  'priority' => 2, 'title' => 'Second weeding', 'notes' => 'Keep the field weed-free.'],
                ['day' => 45,  'priority' => 2, 'title' => 'Top dressing – second CAN application', 'notes' => 'Apply the second round of CAN before tasseling.'],
                ['day' => 60,  'priority' => 3, 'title' => 'Scout for fall armyworm & diseases', 'notes' => 'Check leaves and control pests early.'],
                ['day' => 90,  'priority' => 1, 'title' => 'Check cobbing & grain filling', 'notes' => 'Confirm cobs are forming well.'],
                ['day' => 150, 'priority' => 3, 'title' => 'Harvest maize', 'notes' => 'Harvest once the cobs are dry.'],
            ],
        ],
        [
            'crop' => 'Coffee',
            'name' => 'Coffee – Establishment Year',
            'activities' => [
                ['day' => 0,   'priority' => 3, 'title' => 'Dig holes & transplant seedlings', 'notes' => 'Space at about 2.7m x 2.7m and add manure to each hole.'],
                ['day' => 14,  'priority' => 2, 'title' => 'Mulch and water seedlings', 'notes' => 'Mulch to keep moisture and water during dry spells.'],
                ['day' => 30,  'priority' => 2, 'title' => 'First weeding', 'notes' => 'Keep the ring around each bush weed-free.'],
                ['day' => 90,  'priority' => 2, 'title' => 'First fertilizer application', 'notes' => 'Apply fertilizer to help the young plants grow.'],
                ['day' => 180, 'priority' => 2, 'title' => 'Formative pruning / remove suckers', 'notes' => 'Shape the bush and remove unwanted suckers.'],
                ['day' => 270, 'priority' => 3, 'title' => 'Spray for CBD and leaf rust', 'notes' => 'Protect against coffee berry disease and leaf rust.'],
                ['day' => 365, 'priority' => 2, 'title' => 'Annual fertilizer top-up', 'notes' => 'Feed the plants at the start of the next year.'],
                ['day' => 730, 'priority' => 3, 'title' => 'First harvest – pick ripe red berries', 'notes' => 'Pick only the fully ripe red cherries.'],
            ],
        ],
    ];

    public function run(): void
    {
        $this->command?->info('📅 Seeding default planting schedules...');

        foreach ($this->schedules as $data) {
            $crop = Crop::where('name', $data['crop'])->first();

            if (! $crop) {
                $this->command?->warn("   Skipped '{$data['name']}' – crop '{$data['crop']}' not found. Run CropsSeeder first.");

                continue;
            }

            $schedule = Schedule::firstOrNew([
                'name' => $data['name'],
                'is_system' => true,
            ]);
            $schedule->fill([
                'uuid' => $schedule->uuid ?: (string) Str::orderedUuid(),
                'crop_id' => $crop->id,
                'farmer_id' => null,
                'status' => 1,
            ]);
            $schedule->save();

            foreach ($data['activities'] as $activity) {
                $model = ScheduleActivity::firstOrNew([
                    'schedule_id' => $schedule->id,
                    'activity_name' => $activity['title'],
                ]);
                $model->fill([
                    'uuid' => $model->uuid ?: (string) Str::orderedUuid(),
                    'days_since_planting' => $activity['day'],
                    'priority' => $activity['priority'],
                    'notes' => $activity['notes'],
                    'user_id' => null,
                ]);
                $model->save();
            }
        }

        $this->command?->info('✅ Default planting schedules seeded successfully.');
    }
}
