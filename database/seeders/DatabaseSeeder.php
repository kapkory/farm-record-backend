<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        //        User::factory()->create([
        //            'name' => 'Test User',
        //            'email' => 'test@example.com',
        //        ]);
        // Countries - Global lookup table
        //        $this->command->info('📍 Seeding countries...');
        //        $this->call(CountriesSeeder::class);

        // Global lookup / default data every farmer needs
        $this->call(LedgerAccountsSeeder::class);
        $this->call(CropsSeeder::class);
        $this->call(SchedulesSeeder::class); // depends on CropsSeeder

        $this->command->info('🐄 Seeding animal types...');
        $this->call(AnimalTypesSeeder::class);

        $this->call(TreatmentTypesSeeder::class);
        $this->call(TreatmentPlansSeeder::class); // depends on AnimalTypesSeeder + TreatmentTypesSeeder
    }
}
