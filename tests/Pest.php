<?php

use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Hive;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Builds the full ownership chain used by the bee module tests:
 * User → FarmerUser → Farmer → Farm → AnimalGroup ("Bees" apiary).
 *
 * @return array{user: User, farmer: Farmer, farm: Farm, apiary: AnimalGroup}
 */
function beeTestChain(): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Bee Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);

    FarmerUser::create([
        'farmer_id' => $farmer->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 1,
    ]);

    $farm = Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'Bee Farm',
        'location' => 'Baringo',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $animalType = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Bees',
        'category' => 'apiculture',
        'tracking_mode' => AnimalType::TRACKING_GROUP_ONLY,
        'count_label' => 'hives',
        'status' => 1,
    ]);

    $apiary = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $animalType->id,
        'name' => 'Apiary 1',
        'initial_count' => 0,
        'current_count' => 0,
        'acquired_date' => '2026-01-01',
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'user_id' => $user->id,
        'status' => 1,
    ]);

    return ['user' => $user, 'farmer' => $farmer, 'farm' => $farm, 'apiary' => $apiary];
}

/** Creates a hive directly (bypassing the API) with a manual sequence/code. */
function beeTestHive(array $chain, int $sequence, string $code, array $overrides = []): Hive
{
    return Hive::create(array_merge([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $chain['farm']->id,
        'farmer_id' => $chain['farmer']->id,
        'animal_group_id' => $chain['apiary']->id,
        'sequence' => $sequence,
        'code' => $code,
        'occupancy' => Hive::OCCUPANCY_OCCUPIED,
        'user_id' => $chain['user']->id,
    ], $overrides));
}
