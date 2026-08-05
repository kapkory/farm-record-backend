<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalBreeding;
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

/**
 * Ownership chain for the birth-registration tests:
 * User → FarmerUser → Farmer → Farm, plus a female dam and a male sire of the
 * same type/breed and a pending pregnancy linking them.
 *
 * @return array{
 *     user: User, farmer: Farmer, farm: Farm, type: AnimalType,
 *     breed: AnimalBreed, dam: Animal, sire: Animal, breeding: AnimalBreeding
 * }
 */
function birthTestChain(array $options = []): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Birth Farmer',
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
        'name' => 'Birth Farm',
        'location' => 'Eldoret',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => $options['type_name'] ?? 'Dairy Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $breed = AnimalBreed::create([
        'uuid' => (string) Str::orderedUuid(),
        'animal_type_id' => $type->id,
        'name' => 'Friesian',
        'gestation_days' => $options['gestation_days'] ?? 280,
    ]);

    $makeAnimal = fn (string $name, string $gender) => Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_group_id' => $options['animal_group_id'] ?? null,
        'animal_type_id' => $type->id,
        'animal_breed_id' => $breed->id,
        'tag_number' => 'FC-'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
        'name' => $name,
        'gender' => $gender,
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $dam = $makeAnimal('Bella', 'female');
    $sire = $makeAnimal('Duke', 'male');

    $breeding = AnimalBreeding::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'dam_id' => $dam->id,
        'sire_id' => $sire->id,
        'sire_type' => 'natural',
        'service_date' => $options['service_date'] ?? now()->subDays(280)->toDateString(),
        'status' => 'pending',
        'user_id' => $user->id,
    ]);

    return compact('user', 'farmer', 'farm', 'type', 'breed', 'dam', 'sire', 'breeding');
}

/** URI for the register-birth endpoint of a given breeding. */
function birthUri(AnimalBreeding $breeding): string
{
    return "/api/v1/farms/farm/animals/breedings/{$breeding->uuid}/birth";
}
