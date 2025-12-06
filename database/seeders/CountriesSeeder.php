<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if table already has data
        $count = \DB::table('countries')->count();

        if ($count > 0) {
            $this->command->warn("⚠️  Countries table already has {$count} records.");
            if (!$this->command->confirm('Do you want to re-seed Countries? (This will delete/update existing data)', false)) {
                $this->command->info('⏭️  Skipping Countries seeder...');
                return;
            }
        } else {
            if (!$this->command->confirm('📍 Seed Countries table?', true)) {
                $this->command->info('⏭️  Skipping Countries seeder...');
                return;
            }
        }

        $path = base_path('countries.sql');
        if (!file_exists($path)) {
            $this->command->error("countries.sql not found at project root: {$path}");
            return;
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            $this->command->error("countries.sql is empty or unreadable.");
            return;
        }

        // Extract only INSERT lines for countries and retarget them to the 'countries' table
        if (!preg_match_all('/INSERT INTO `countries`[^;]*;\s*/s', $sql, $matches)) {
            $this->command->error("No INSERT statements for countries were found in countries.sql.");
            return;
        }

        $inserts = array_map(function ($stmt) {
            // Replace the table name to match our migrated table
            return str_replace('`countries`', '`countries`', $stmt);
        }, $matches[0]);

        $insertSql = implode("\n", $inserts);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // Clean table before import to avoid duplicates
        DB::table('countries')->truncate();

        // Execute the mass insert statements
        DB::unprepared($insertSql);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $finalCount = \DB::table('countries')->count();
        $this->command->info("✅ Countries seeded successfully! ({$finalCount} records)");
    }
}
