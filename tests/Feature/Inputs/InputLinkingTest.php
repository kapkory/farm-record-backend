<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\FarmInput;
use App\Models\Core\Task;
use App\Models\Core\TreatmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const TREATMENTS_URI = '/api/v1/farms/farm/crops/treatments';
const TASKS_URI = '/api/v1/tasks';

function inputLinkChain(): array
{
    $user = User::factory()->create();
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(), 'display_name' => 'F', 'type' => 'individual', 'status' => 1,
    ]);
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 1]);
    $farm = Farm::create([
        'uuid' => (string) Str::orderedUuid(), 'farmer_id' => $farmer->id, 'name' => 'F', 'location' => 'X',
        'type' => 'animal', 'ownership_type' => 'owned', 'status' => 1,
    ]);
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(), 'name' => 'Cattle', 'category' => 'livestock', 'tracking_mode' => 'both',
    ]);
    $animal = Animal::create([
        'uuid' => (string) Str::orderedUuid(), 'farm_id' => $farm->id, 'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id, 'name' => 'Bahati', 'gender' => 'female', 'status' => 'active', 'user_id' => $user->id,
    ]);
    $treatmentType = TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(), 'name' => 'Deworming', 'type' => 'livestock', 'status' => 1,
    ]);
    $input = FarmInput::create([
        'uuid' => (string) Str::orderedUuid(), 'farm_id' => $farm->id, 'farmer_id' => $farmer->id,
        'name' => 'Triatix', 'category' => 'dip', 'treatment_type_id' => $treatmentType->id,
        'quantity' => 500, 'unit' => 'ml', 'quantity_remaining' => 500,
        'total_cost' => 3500, 'unit_cost' => 7, 'purchase_date' => '2026-07-01', 'user_id' => $user->id,
    ]);

    return compact('user', 'farmer', 'farm', 'type', 'animal', 'treatmentType', 'input');
}

it('draws stock and attributes cost when a treatment is recorded from an input', function () {
    $c = inputLinkChain();

    $response = $this->actingAs($c['user'], 'sanctum')->postJson(TREATMENTS_URI, [
        'model' => 'animal',
        'animal_uuid' => $c['animal']->uuid,
        'treatment_type_id' => $c['treatmentType']->id,
        'details' => 'Monthly deworming',
        'date' => '2026-08-01',
        'input_uuid' => $c['input']->uuid,
        'input_quantity_used' => 20,
    ]);

    $response->assertCreated();

    // Exactly one treatment (no duplicate from the input's auto-treatment).
    $this->assertDatabaseCount('treatments', 1);
    // Stock drawn down: 500 - 20 = 480.
    expect((float) $c['input']->fresh()->quantity_remaining)->toBe(480.0);
    // One application, its target holding the attributed cost (20 * 7 = 140).
    $this->assertDatabaseCount('input_applications', 1);
    $this->assertDatabaseHas('input_application_targets', [
        'targetable_type' => Animal::class,
        'targetable_id' => $c['animal']->id,
        'allocated_cost' => '140.00',
    ]);
    // Input applications never post to the ledger (cost was posted at purchase).
    $this->assertDatabaseCount('ledger_transactions', 0);
});

it('rejects using more stock than is left', function () {
    $c = inputLinkChain();

    $this->actingAs($c['user'], 'sanctum')->postJson(TREATMENTS_URI, [
        'model' => 'animal',
        'animal_uuid' => $c['animal']->uuid,
        'treatment_type_id' => $c['treatmentType']->id,
        'details' => 'Overdose',
        'date' => '2026-08-01',
        'input_uuid' => $c['input']->uuid,
        'input_quantity_used' => 999,
    ])->assertStatus(422);

    // Nothing partially applied.
    $this->assertDatabaseCount('treatments', 0);
    expect((float) $c['input']->fresh()->quantity_remaining)->toBe(500.0);
});

it('does not allow drawing stock for a crop treatment', function () {
    $c = inputLinkChain();

    $this->actingAs($c['user'], 'sanctum')->postJson(TREATMENTS_URI, [
        'model' => 'planting',
        'planting_uuid' => (string) Str::orderedUuid(),
        'treatment_type_id' => $c['treatmentType']->id,
        'details' => 'x',
        'date' => '2026-08-01',
        'input_uuid' => $c['input']->uuid,
        'input_quantity_used' => 5,
    ])->assertStatus(422);
});

it('draws stock when a task on an animal is completed with an input', function () {
    $c = inputLinkChain();

    $created = $this->actingAs($c['user'], 'sanctum')->postJson(TASKS_URI, [
        'title' => 'Spray the cow',
        'taskable_type' => 'animal',
        'taskable_uuid' => $c['animal']->uuid,
    ])->assertCreated();

    $taskUuid = $created->json('data.uuid');

    $this->actingAs($c['user'], 'sanctum')->putJson(TASKS_URI."/{$taskUuid}", [
        'title' => 'Spray the cow',
        'taskable_type' => 'animal',
        'taskable_uuid' => $c['animal']->uuid,
        'input_uuid' => $c['input']->uuid,
        'input_quantity_used' => 10,
    ])->assertOk();

    expect((float) $c['input']->fresh()->quantity_remaining)->toBe(490.0);
    $this->assertDatabaseCount('input_applications', 1);
    // A task is not a treatment, so no health record is written.
    $this->assertDatabaseCount('treatments', 0);
});
