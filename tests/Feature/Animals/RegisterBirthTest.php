<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalEvent;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Task;
use App\Models\Core\TreatmentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates an animal for each live offspring and closes the pregnancy', function () {
    ['user' => $user, 'farm' => $farm, 'dam' => $dam, 'sire' => $sire, 'breeding' => $breeding, 'breed' => $breed] = birthTestChain();

    $response = $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'uuid' => (string) Str::orderedUuid(),
        'birth_date' => now()->toDateString(),
        'stillborn_count' => 1,
        'offspring' => [
            ['uuid' => (string) Str::orderedUuid(), 'gender' => 'female', 'name' => 'Daisy'],
            ['uuid' => (string) Str::orderedUuid(), 'gender' => 'male', 'tag_number' => 'FC-777777'],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.status', 'born')
        ->assertJsonPath('data.offspring_count', 2)
        ->assertJsonPath('data.stillborn_count', 1)
        ->assertJsonPath('data.actual_birth_date', now()->toDateString())
        ->assertJsonCount(2, 'data.offspring');

    $offspring = Animal::where('animal_breeding_id', $breeding->id)->get();
    expect($offspring)->toHaveCount(2);

    foreach ($offspring as $calf) {
        expect($calf->dam_id)->toBe($dam->id)
            ->and($calf->sire_id)->toBe($sire->id)
            ->and($calf->farm_id)->toBe($farm->id)
            ->and($calf->animal_type_id)->toBe($dam->animal_type_id)
            ->and($calf->animal_breed_id)->toBe($breed->id)
            ->and($calf->acquisition_type)->toBe('born')
            ->and($calf->status)->toBe('active')
            ->and($calf->date_of_birth->toDateString())->toBe(now()->toDateString());
    }

    // A blank tag falls back to the next number in the FC- sequence.
    expect($offspring->firstWhere('name', 'Daisy')->tag_number)->toStartWith('FC-')
        ->and($offspring->firstWhere('tag_number', 'FC-777777')->name)->toBe('FC-777777');

    $breeding->refresh();
    expect($breeding->status)->toBe('born')
        ->and($breeding->offspring_count)->toBe(2)
        ->and($breeding->stillborn_count)->toBe(1)
        ->and($breeding->birth_event_id)->not->toBeNull();

    $event = AnimalEvent::find($breeding->birth_event_id);
    expect($event->event_type)->toBe('birth')
        ->and($event->quantity)->toBe(2)
        ->and($event->eventable_id)->toBe($dam->id)
        ->and($event->metadata['stillborn_count'])->toBe(1);
});

it('records a stillborn-only birth without creating any animal', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'stillborn_count' => 2,
        'offspring' => [],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'born')
        ->assertJsonPath('data.offspring_count', 0)
        ->assertJsonPath('data.stillborn_count', 2);

    expect(Animal::where('animal_breeding_id', $breeding->id)->count())->toBe(0);
});

it('answers a replayed create from the stored birth instead of doubling the litter', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $payload = [
        'uuid' => (string) Str::orderedUuid(),
        'birth_date' => now()->toDateString(),
        'offspring' => [['uuid' => (string) Str::orderedUuid(), 'gender' => 'female']],
    ];

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), $payload)->assertCreated();
    // The offline queue retrying the same create.
    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), $payload)->assertOk();

    expect(Animal::where('animal_breeding_id', $breeding->id)->count())->toBe(1)
        ->and(AnimalEvent::where('event_type', 'birth')->count())->toBe(1);
});

it('grows the herd count when the dam belongs to a group', function () {
    ['user' => $user, 'farm' => $farm, 'farmer' => $farmer] = $seed = birthTestChain();

    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $seed['type']->id,
        'name' => 'Milking herd',
        'initial_count' => 10,
        'current_count' => 10,
        'acquired_date' => now()->subYear()->toDateString(),
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'user_id' => $user->id,
        'status' => 1,
    ]);
    $seed['dam']->update(['animal_group_id' => $group->id]);

    $this->actingAs($user, 'sanctum')->postJson(birthUri($seed['breeding']), [
        'birth_date' => now()->toDateString(),
        'offspring' => [
            ['gender' => 'female'],
            ['gender' => 'male'],
        ],
    ])->assertCreated();

    expect($group->fresh()->current_count)->toBe(12);
});

it('passes the dam vaccination plan on to the newborn when asked', function () {
    ['user' => $user, 'dam' => $dam, 'breeding' => $breeding] = birthTestChain();

    $plan = $this->actingAs($user, 'sanctum')->postJson('/api/v1/settings/animals/treatment-plans', [
        'name' => 'Calf schedule',
        'activities' => [
            ['vaccine' => 'Brucellosis', 'offset_value' => 30, 'offset_unit' => 'days', 'priority' => 3],
        ],
    ])->assertCreated()->json('data');

    $dam->update(['treatment_plan_id' => $plan['id'] ?? TreatmentPlan::where('uuid', $plan['uuid'])->value('id')]);

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'inherit_treatment_plan' => true,
        'offspring' => [['gender' => 'female']],
    ])->assertCreated();

    $calf = Animal::where('animal_breeding_id', $breeding->id)->first();
    expect($calf->treatment_plan_id)->toBe($dam->fresh()->treatment_plan_id);

    // AnimalObserver turns the plan into tasks dated from the newborn's DOB.
    $task = Task::where('taskable_id', $calf->id)->where('title', 'Brucellosis')->first();
    expect($task)->not->toBeNull()
        ->and($task->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());
});

it('leaves the newborn without a plan when the farmer opts out', function () {
    ['user' => $user, 'dam' => $dam, 'farmer' => $farmer, 'breeding' => $breeding] = birthTestChain();

    $plan = TreatmentPlan::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Unused schedule',
        'farmer_id' => $farmer->id,
    ]);
    $dam->update(['treatment_plan_id' => $plan->id]);

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'inherit_treatment_plan' => false,
        'offspring' => [['gender' => 'female']],
    ])->assertCreated();

    expect(Animal::where('animal_breeding_id', $breeding->id)->first()->treatment_plan_id)->toBeNull();
});

it('rejects a birth date before the service date', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain([
        'service_date' => now()->subDays(30)->toDateString(),
    ]);

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->subDays(60)->toDateString(),
        'offspring' => [['gender' => 'female']],
    ])->assertStatus(422)->assertJsonValidationErrors('birth_date');
});

it('rejects a future birth date', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->addDay()->toDateString(),
        'offspring' => [['gender' => 'female']],
    ])->assertStatus(422)->assertJsonValidationErrors('birth_date');
});

it('rejects a birth with neither live nor stillborn offspring', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'offspring' => [],
        'stillborn_count' => 0,
    ])->assertStatus(422)->assertJsonValidationErrors('offspring');
});

it('requires the sex of every offspring', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'offspring' => [['name' => 'Nameless']],
    ])->assertStatus(422)->assertJsonValidationErrors('offspring.0.gender');
});

it('rejects a second birth on an already resolved pregnancy', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'offspring' => [['gender' => 'female']],
    ])->assertCreated();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'uuid' => (string) Str::orderedUuid(),
        'birth_date' => now()->toDateString(),
        'offspring' => [['gender' => 'male']],
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

it('hides a breeding on another farm behind a 404', function () {
    ['breeding' => $breeding] = birthTestChain();
    ['user' => $outsider] = birthTestChain();

    $this->actingAs($outsider, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'offspring' => [['gender' => 'female']],
    ])->assertNotFound();
});

it('will not let the plain update endpoint mark a pregnancy born', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/farms/farm/animals/breedings/{$breeding->uuid}", ['status' => 'born'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');

    expect($breeding->fresh()->status)->toBe('pending');
});

it('completes the birthing task when the birth is registered', function () {
    ['user' => $user, 'breeding' => $breeding] = birthTestChain();

    $taskId = $breeding->fresh()->birth_task_id;
    expect($taskId)->not->toBeNull();

    $this->actingAs($user, 'sanctum')->postJson(birthUri($breeding), [
        'birth_date' => now()->toDateString(),
        'offspring' => [['gender' => 'female']],
    ])->assertCreated();

    expect(Task::find($taskId)->task_status)->toBe(Task::STATUS_COMPLETED);
});
