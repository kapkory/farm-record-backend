<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\User;
use App\Services\Animals\GestationEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const BREEDINGS_URI = '/api/v1/farms/farm/animals/breedings';

function breedingContext(?int $breedGestation = null, string $typeName = 'Dairy Cattle'): array
{
    $user = User::factory()->create();
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Breeder',
        'type' => 'individual',
        'status' => 1,
    ]);
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 1]);
    $farm = Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'Breeding Farm',
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => $typeName,
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);
    $breed = AnimalBreed::create([
        'uuid' => (string) Str::orderedUuid(),
        'animal_type_id' => $type->id,
        'name' => 'Test Breed',
        'gestation_days' => $breedGestation,
    ]);
    $dam = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'animal_breed_id' => $breed->id,
        'name' => 'Dam',
        'gender' => 'female',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    return [$user, $farm, $dam];
}

it('uses the breed gestation period when it is set', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);

    $this->actingAs($user, 'sanctum')->postJson(BREEDINGS_URI, [
        'farm_uuid' => $farm->uuid,
        'dam_id' => $dam->uuid,
        'sire_type' => 'ai',
        'ai_bull_name' => 'Champion',
        'service_date' => '2026-01-01',
    ])->assertCreated()
        ->assertJsonPath('data.expected_birth_date', '2026-10-08'); // +280 days
});

it('falls back to a species default when the breed has no gestation set', function () {
    // No breed gestation, but the type name contains "goat".
    [$user, $farm, $dam] = breedingContext(breedGestation: null, typeName: 'Dairy Goat');

    $this->actingAs($user, 'sanctum')->postJson(BREEDINGS_URI, [
        'farm_uuid' => $farm->uuid,
        'dam_id' => $dam->uuid,
        'sire_type' => 'ai',
        'ai_bull_name' => 'Buck',
        'service_date' => '2026-01-01',
    ])->assertCreated()
        ->assertJsonPath('data.expected_birth_date', '2026-05-31'); // +150 days
});

it('keeps a farmer-entered birth date instead of estimating', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);

    $this->actingAs($user, 'sanctum')->postJson(BREEDINGS_URI, [
        'farm_uuid' => $farm->uuid,
        'dam_id' => $dam->uuid,
        'sire_type' => 'ai',
        'ai_bull_name' => 'Champion',
        'service_date' => '2026-01-01',
        'expected_birth_date' => '2026-09-30',
    ])->assertCreated()
        ->assertJsonPath('data.expected_birth_date', '2026-09-30');
});

it('applies the per-animal gestation adjustment', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);
    $dam->update(['gestation_adjustment_days' => 5]);

    $estimator = app(GestationEstimator::class);
    expect($estimator->daysFor($dam->fresh()->load('animalBreed', 'animalType')))->toBe(285);
});

it('exposes the effective gestation days on the animal payload', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/livestocks/{$dam->uuid}")
        ->assertOk()
        ->assertJsonPath('data.gestation_days', 280);
});

it('flags half siblings and clears unrelated pairs in the inbreeding check', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);

    // A shared mother makes two animals half-siblings.
    $shared = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $dam->farmer_id,
        'animal_type_id' => $dam->animal_type_id,
        'name' => 'Grandma',
        'gender' => 'female',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $sisterA = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id, 'farmer_id' => $dam->farmer_id, 'animal_type_id' => $dam->animal_type_id,
        'name' => 'Sister A', 'gender' => 'female', 'status' => 'active', 'user_id' => $user->id,
        'dam_id' => $shared->id,
    ]);
    $brotherB = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id, 'farmer_id' => $dam->farmer_id, 'animal_type_id' => $dam->animal_type_id,
        'name' => 'Brother B', 'gender' => 'male', 'status' => 'active', 'user_id' => $user->id,
        'dam_id' => $shared->id,
    ]);

    // Half-siblings → related, medium severity.
    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/breedings/inbreeding-check?dam_uuid={$sisterA->uuid}&sire_uuid={$brotherB->uuid}")
        ->assertOk()
        ->assertJsonPath('data.related', true)
        ->assertJsonPath('data.severity', 'medium')
        ->assertJsonPath('data.relationship', 'half-siblings');

    // An unrelated animal → not related.
    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/breedings/inbreeding-check?dam_uuid={$sisterA->uuid}&sire_uuid={$dam->uuid}")
        ->assertOk()
        ->assertJsonPath('data.related', false);
});

it('flags a parent bred to its offspring', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);

    $offspring = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id, 'farmer_id' => $dam->farmer_id, 'animal_type_id' => $dam->animal_type_id,
        'name' => 'Calf', 'gender' => 'male', 'status' => 'active', 'user_id' => $user->id,
        'dam_id' => $dam->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/breedings/inbreeding-check?dam_uuid={$dam->uuid}&sire_uuid={$offspring->uuid}")
        ->assertOk()
        ->assertJsonPath('data.related', true)
        ->assertJsonPath('data.severity', 'high')
        ->assertJsonPath('data.relationship', 'parent-offspring');
});

it('lists pending breedings due within the window on the calendar', function () {
    [$user, $farm, $dam] = breedingContext(breedGestation: 280);

    // Due in ~10 days (service date + 280 lands inside a 45-day window? No —
    // set an explicit expected date via service date math). Use a manual date.
    AnimalBreeding::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'dam_id' => $dam->id,
        'sire_type' => 'ai',
        'ai_bull_name' => 'Champ',
        'service_date' => now()->subDays(270)->toDateString(),
        'expected_birth_date' => now()->addDays(10)->toDateString(),
        'status' => 'pending',
        'user_id' => $user->id,
    ]);
    // Far future — outside the default window.
    AnimalBreeding::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'dam_id' => $dam->id,
        'sire_type' => 'ai',
        'ai_bull_name' => 'Champ',
        'service_date' => now()->toDateString(),
        'expected_birth_date' => now()->addDays(120)->toDateString(),
        'status' => 'pending',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/farms/farm/animals/breedings/calendar')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
