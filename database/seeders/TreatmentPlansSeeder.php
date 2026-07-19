<?php

namespace Database\Seeders;

use App\Models\Core\AnimalType;
use App\Models\Core\TreatmentPlan;
use App\Models\Core\TreatmentPlanActivity;
use App\Models\Core\TreatmentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the default "Layers Chicken Vaccination Schedule" (day-old to point of
 * lay) as a global system treatment plan for Poultry. When a farmer creates a
 * flock against this plan, AnimalGroupObserver generates a dated vaccination
 * Task for each step. Depends on AnimalTypesSeeder and TreatmentTypesSeeder.
 *
 * age_days is the bird's age (from acquired_date) when the dose is due.
 */
class TreatmentPlansSeeder extends Seeder
{
    protected string $planName = 'Layers Chicken Vaccination Schedule';

    /**
     * @var array<int, array{age_days: int, vaccine: string, type_name: string, disease: string, route: string}>
     */
    protected array $activities = [
        ['age_days' => 1,   'vaccine' => "Marek's Vaccine",                'type_name' => "Marek's Vaccine",                'disease' => "Marek's Disease",                  'route' => 'Subcutaneous (under the skin)'],
        ['age_days' => 7,   'vaccine' => 'Gumboro Vaccine (IBD)',          'type_name' => 'Gumboro Vaccine (IBD)',          'disease' => 'Gumboro (Infectious Bursal Disease)', 'route' => 'Drinking Water'],
        ['age_days' => 14,  'vaccine' => 'Newcastle Vaccine (Lasota)',     'type_name' => 'Newcastle Vaccine (Lasota)',     'disease' => 'Newcastle Disease',                'route' => 'Eye drop / Drinking Water'],
        ['age_days' => 21,  'vaccine' => 'Gumboro Vaccine (Booster)',      'type_name' => 'Gumboro Vaccine (IBD)',          'disease' => 'Gumboro (Infectious Bursal Disease)', 'route' => 'Drinking Water'],
        ['age_days' => 28,  'vaccine' => 'Newcastle Vaccine (Lasota Booster)', 'type_name' => 'Newcastle Vaccine (Lasota)', 'disease' => 'Newcastle Disease',                'route' => 'Drinking Water'],
        ['age_days' => 42,  'vaccine' => 'Fowl Pox Vaccine',              'type_name' => 'Fowl Pox Vaccine',              'disease' => 'Fowl Pox',                         'route' => 'Wing Web (wing stab)'],
        ['age_days' => 49,  'vaccine' => 'Fowl Typhoid Vaccine',          'type_name' => 'Fowl Typhoid Vaccine',          'disease' => 'Fowl Typhoid',                     'route' => 'Injection (Subcutaneous)'],
        ['age_days' => 56,  'vaccine' => 'Fowl Cholera Vaccine',          'type_name' => 'Fowl Cholera Vaccine',          'disease' => 'Fowl Cholera',                     'route' => 'Injection (Subcutaneous)'],
        ['age_days' => 112, 'vaccine' => 'Newcastle Vaccine (Komarov)',   'type_name' => 'Newcastle Vaccine (Komarov)',   'disease' => 'Newcastle Disease',                'route' => 'Injection (Intramuscular)'],
        ['age_days' => 126, 'vaccine' => 'Egg Drop Syndrome Vaccine (EDS)', 'type_name' => 'Egg Drop Syndrome Vaccine (EDS)', 'disease' => 'Egg Drop Syndrome (EDS)',       'route' => 'Injection (Intramuscular)'],
    ];

    public function run(): void
    {
        $this->command?->info('🐔 Seeding default poultry vaccination plan...');

        $poultry = AnimalType::where('name', 'Poultry')->first();

        if (! $poultry) {
            $this->command?->warn("   Skipped '{$this->planName}' – 'Poultry' animal type not found. Run AnimalTypesSeeder first.");

            return;
        }

        $plan = TreatmentPlan::firstOrNew([
            'name' => $this->planName,
            'is_system' => true,
        ]);
        $plan->fill([
            'uuid' => $plan->uuid ?: (string) Str::orderedUuid(),
            'animal_type_id' => $poultry->id,
            'farmer_id' => null,
            'status' => 1,
        ]);
        $plan->save();

        // Resolve treatment type names to ids once.
        $typeIds = TreatmentType::whereIn('name', collect($this->activities)->pluck('type_name')->unique())
            ->pluck('id', 'name');

        foreach ($this->activities as $activity) {
            $model = TreatmentPlanActivity::firstOrNew([
                'treatment_plan_id' => $plan->id,
                'vaccine' => $activity['vaccine'],
            ]);
            $model->fill([
                'uuid' => $model->uuid ?: (string) Str::orderedUuid(),
                'treatment_type_id' => $typeIds[$activity['type_name']] ?? null,
                'disease' => $activity['disease'],
                'route' => $activity['route'],
                'age_days' => $activity['age_days'],
                'priority' => 3, // vaccinations are high priority
                'user_id' => null,
            ]);
            $model->save();
        }

        $this->command?->info('✅ Default poultry vaccination plan seeded successfully.');
    }
}
