<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalType;
use App\Models\Core\Task;
use App\Models\Core\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const PLANS_URI = '/api/v1/settings/animals/treatment-plans';
const ANIMALS_URI = '/api/v1/farms/farm/animals';

it('creates a treatment plan with activities converted to age days', function () {
    $chain = beeTestChain();

    $response = $this->actingAs($chain['user'], 'sanctum')->postJson(PLANS_URI, [
        'name' => 'Layers Vaccination Schedule',
        'activities' => [
            ['vaccine' => "Marek's", 'disease' => "Marek's disease", 'offset_value' => 1, 'offset_unit' => 'days', 'priority' => 3],
            ['vaccine' => 'Gumboro', 'offset_value' => 2, 'offset_unit' => 'weeks', 'priority' => 3, 'route' => 'Drinking water'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Layers Vaccination Schedule')
        ->assertJsonPath('data.is_system', false)
        ->assertJsonCount(2, 'data.activities');

    $this->assertDatabaseHas('treatment_plan_activities', ['vaccine' => 'Gumboro', 'age_days' => 14]);
});

it('updates a plan by syncing activities and rejects edits to system plans', function () {
    $chain = beeTestChain();

    $create = $this->actingAs($chain['user'], 'sanctum')->postJson(PLANS_URI, [
        'name' => 'My Plan',
        'activities' => [
            ['vaccine' => 'A', 'offset_value' => 1, 'offset_unit' => 'days', 'priority' => 2],
            ['vaccine' => 'B', 'offset_value' => 7, 'offset_unit' => 'days', 'priority' => 2],
        ],
    ])->assertCreated();

    $uuid = $create->json('data.uuid');
    $keptId = collect($create->json('data.activities'))->firstWhere('vaccine', 'A')['id'];

    // Keep A (renamed), drop B, add C.
    $this->actingAs($chain['user'], 'sanctum')->putJson(PLANS_URI."/{$uuid}", [
        'name' => 'My Plan v2',
        'activities' => [
            ['id' => $keptId, 'vaccine' => 'A2', 'offset_value' => 2, 'offset_unit' => 'days', 'priority' => 2],
            ['vaccine' => 'C', 'offset_value' => 3, 'offset_unit' => 'weeks', 'priority' => 1],
        ],
    ])->assertOk()->assertJsonCount(2, 'data.activities');

    $this->assertDatabaseHas('treatment_plan_activities', ['id' => $keptId, 'vaccine' => 'A2', 'age_days' => 2]);
    $this->assertSoftDeleted('treatment_plan_activities', ['vaccine' => 'B']);

    $system = TreatmentPlan::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'System Plan',
        'is_system' => true,
        'status' => 1,
    ]);

    $this->actingAs($chain['user'], 'sanctum')->putJson(PLANS_URI."/{$system->uuid}", [
        'name' => 'Hacked',
        'activities' => [['vaccine' => 'X', 'offset_value' => 1, 'offset_unit' => 'days', 'priority' => 2]],
    ])->assertForbidden();
});

it('hides other farmers plans from update and delete', function () {
    $chain = beeTestChain();

    $uuid = $this->actingAs($chain['user'], 'sanctum')->postJson(PLANS_URI, [
        'name' => 'Private Plan',
        'activities' => [['vaccine' => 'A', 'offset_value' => 1, 'offset_unit' => 'days', 'priority' => 2]],
    ])->json('data.uuid');

    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')->putJson(PLANS_URI."/{$uuid}", [
        'name' => 'Stolen',
        'activities' => [['vaccine' => 'X', 'offset_value' => 1, 'offset_unit' => 'days', 'priority' => 2]],
    ])->assertNotFound();

    $this->actingAs($outsider, 'sanctum')->deleteJson(PLANS_URI."/{$uuid}")->assertNotFound();
    $this->actingAs($chain['user'], 'sanctum')->deleteJson(PLANS_URI."/{$uuid}")->assertOk();
    $this->assertSoftDeleted('treatment_plans', ['uuid' => $uuid]);
});

it('generates dated tasks when an individual animal is created with a plan', function () {
    $chain = beeTestChain();

    $cattle = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => AnimalType::TRACKING_BOTH,
        'status' => 1,
    ]);

    $plan = TreatmentPlan::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Calf Plan',
        'farmer_id' => $chain['farmer']->id,
        'status' => 1,
    ]);
    $plan->activities()->createMany([
        ['uuid' => (string) Str::orderedUuid(), 'vaccine' => 'Blackleg', 'age_days' => 90, 'priority' => 3],
        ['uuid' => (string) Str::orderedUuid(), 'vaccine' => 'FMD', 'age_days' => 180, 'priority' => 3],
    ]);

    $this->actingAs($chain['user'], 'sanctum')->postJson(ANIMALS_URI, [
        'farm_uuid' => $chain['farm']->uuid,
        'animal_type_id' => $cattle->id,
        'name' => 'Bessie',
        'gender' => 'female',
        'date_of_birth' => '2026-07-01',
        'treatment_plan_uuid' => $plan->uuid,
    ])->assertCreated();

    $animal = Animal::where('name', 'Bessie')->firstOrFail();
    expect($animal->treatment_plan_id)->toBe($plan->id);

    $tasks = Task::where('taskable_type', $animal->getMorphClass())
        ->where('taskable_id', $animal->id)
        ->orderBy('due_date')
        ->get();

    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]->title)->toBe('Blackleg')
        ->and($tasks[0]->due_date->toDateString())->toBe('2026-09-29')  // +90 days
        ->and($tasks[1]->due_date->toDateString())->toBe('2026-12-28'); // +180 days
});
