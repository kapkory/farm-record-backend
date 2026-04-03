<?php

use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalType;
use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Task;
use App\Models\Core\TreatmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const ANIMAL_TYPES_URI = '/api/v1/settings/animals/animal-types';
const ANIMAL_GROUPS_URI = '/api/v1/farms/farm/animal-groups';
const ANIMALS_URI = '/api/v1/farms/farm/animals';
const ANIMAL_EVENTS_URI = '/api/v1/farms/farm/animal-events';
const PRODUCTIONS_URI = '/api/v1/farms/farm/productions';
const TREATMENTS_URI = '/api/v1/farms/farm/crops/treatments';
const TASKS_URI_ANIMAL = '/api/v1/tasks';

function animalContext(): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Animal Farmer',
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
        'name' => 'Green Valley Farm',
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    return [$user, $farmer, $farm];
}

it('creates an animal type from the settings endpoint', function () {
    [$user] = animalContext();

    $response = $this->actingAs($user, 'sanctum')->postJson(ANIMAL_TYPES_URI, [
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
        'count_label' => 'heads',
        'description' => 'Large ruminants',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Cattle')
        ->assertJsonPath('data.tracking_mode', 'both');

    $this->assertDatabaseHas('animal_types', ['name' => 'Cattle', 'tracking_mode' => 'both']);
});

it('creates an animal group and lists it for the farm', function () {
    [$user, , $farm] = animalContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
        'count_label' => 'heads',
        'status' => 1,
    ]);

    $createResponse = $this->actingAs($user, 'sanctum')->postJson(ANIMAL_GROUPS_URI, [
        'farm_uuid' => $farm->uuid,
        'animal_type_id' => $type->id,
        'name' => 'Dairy Herd A',
        'initial_count' => 10,
        'acquired_date' => '2026-03-20',
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'description' => 'Main dairy herd',
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('data.name', 'Dairy Herd A')
        ->assertJsonPath('data.current_count', 10)
        ->assertJsonPath('data.animal_type', 'Cattle');

    $this->actingAs($user, 'sanctum')
        ->getJson(ANIMAL_GROUPS_URI.'/list/'.$farm->uuid)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Dairy Herd A');
});

it('rejects creating an individual animal for a group-only type', function () {
    [$user, , $farm] = animalContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Bees',
        'category' => 'apiculture',
        'tracking_mode' => 'group_only',
        'count_label' => 'hives',
        'status' => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson(ANIMALS_URI, [
        'farm_uuid' => $farm->uuid,
        'animal_type_id' => $type->id,
        'tag_id' => 'BEE-001',
        'name' => 'Queen Bee',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['animal_type_id']);
});

it('updates animal group count when an animal event is recorded', function () {
    [$user, , $farm] = animalContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Goats',
        'category' => 'livestock',
        'tracking_mode' => 'both',
        'count_label' => 'animals',
        'status' => 1,
    ]);

    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'animal_type_id' => $type->id,
        'name' => 'Main Herd',
        'initial_count' => 5,
        'current_count' => 5,
        'acquired_date' => '2026-03-01',
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'user_id' => $user->id,
        'status' => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson(ANIMAL_EVENTS_URI, [
        'eventable_type' => 'animal_group',
        'eventable_uuid' => $group->uuid,
        'event_type' => 'birth',
        'date' => '2026-03-30',
        'quantity' => 3,
        'description' => 'Three kids born',
        'metadata' => ['health_status' => 'healthy'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.event_type', 'birth')
        ->assertJsonPath('data.quantity', 3);

    expect($group->fresh()->current_count)->toBe(8);
});

it('supports animal groups in tasks productions and treatments', function () {
    [$user, , $farm] = animalContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Poultry',
        'category' => 'poultry',
        'tracking_mode' => 'group_only',
        'count_label' => 'birds',
        'status' => 1,
    ]);

    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'animal_type_id' => $type->id,
        'name' => 'Layer House A',
        'initial_count' => 100,
        'current_count' => 100,
        'acquired_date' => '2026-03-01',
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'user_id' => $user->id,
        'status' => 1,
    ]);

    TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Vaccination',
        'description' => 'Bird vaccination',
        'type' => 'livestock',
        'status' => 1,
    ]);

    $this->actingAs($user, 'sanctum')->postJson(TASKS_URI_ANIMAL, [
        'title' => 'Vaccinate the flock',
        'taskable_type' => 'animal_group',
        'taskable_uuid' => $group->uuid,
        'priority' => Task::PRIORITY_HIGH,
    ])->assertCreated();

    $this->assertDatabaseHas('tasks', [
        'taskable_type' => App\Models\Core\AnimalGroup::class,
        'taskable_id' => $group->id,
        'title' => 'Vaccinate the flock',
    ]);

    $this->actingAs($user, 'sanctum')->postJson(PRODUCTIONS_URI.'/store', [
        'productionable_type' => 'animal_group',
        'productionable_uuid' => $group->uuid,
        'name' => 'Egg Collection',
        'date' => '2026-03-25',
        'quantity' => 80,
        'unit' => 'trays',
        'notes' => 'Morning collection',
    ])->assertCreated()
      ->assertJsonPath('data.productionable_type', 'animal_group');

    $treatmentTypeId = TreatmentType::where('name', 'Vaccination')->value('id');

    $this->actingAs($user, 'sanctum')->postJson(TREATMENTS_URI, [
        'model' => 'animal_group',
        'animal_group_uuid' => $group->uuid,
        'treatment_type_id' => $treatmentTypeId,
        'details' => 'Newcastle vaccination',
        'date' => '2026-03-26',
        'notes' => 'Completed house A',
        'record_expense' => false,
    ])->assertCreated();

    $this->assertDatabaseHas('treatments', [
        'treatmentable_type' => App\Models\Core\AnimalGroup::class,
        'treatmentable_id' => $group->id,
        'details' => 'Newcastle vaccination',
    ]);
});

