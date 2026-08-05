<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalType;
use App\Models\Core\AnimalWeight;
use App\Models\Core\Task;
use App\Services\Animals\WeighingIntervalResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const WEIGHTS_URI = '/api/v1/farms/farm/animals/weights';

/** A group on the same farm as the chain's dam, for sample-weighing tests. */
function weightTestGroup(array $chain, array $overrides = []): AnimalGroup
{
    return AnimalGroup::create(array_merge([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $chain['farm']->id,
        'farmer_id' => $chain['farmer']->id,
        'animal_type_id' => $chain['type']->id,
        'name' => 'Broiler batch 1',
        'initial_count' => 200,
        'current_count' => 200,
        'acquired_date' => now()->subMonth()->toDateString(),
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'user_id' => $chain['user']->id,
        'status' => 1,
    ], $overrides));
}

/** The weighing task a reading booked (never the birthing task on the dam). */
function weighingTaskFor(AnimalWeight $weight): ?Task
{
    return Task::find($weight->fresh()->next_task_id);
}

it('stores an individual weighing as entered', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 242.5,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.weight_kg', 242.5)
        ->assertJsonPath('data.sample_size', 1)
        ->assertJsonPath('data.is_sample', false)
        ->assertJsonPath('data.sample_total_kg', null);
});

it('converts grams to kilograms but remembers what the farmer typed', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 850,
        'entered_unit' => 'g',
        'sample_size' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.weight_kg', 0.85)
        ->assertJsonPath('data.entered_value', 850)
        ->assertJsonPath('data.entered_unit', 'g');
});

it('converts pounds to kilograms', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 100,
        'entered_unit' => 'lb',
        'sample_size' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.weight_kg', 45.359);
});

it('divides a group sample down to a weight per head', function () {
    $chain = birthTestChain();
    $group = weightTestGroup($chain);

    $this->actingAs($chain['user'], 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal_group',
        'weighable_uuid' => $group->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 18.4,
        'entered_unit' => 'kg',
        'sample_size' => 10,
    ])->assertCreated()
        ->assertJsonPath('data.weight_kg', 1.84)
        ->assertJsonPath('data.sample_total_kg', 18.4)
        ->assertJsonPath('data.sample_size', 10)
        ->assertJsonPath('data.is_sample', true);
});

it('answers a replayed create from the stored reading', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $payload = [
        'uuid' => (string) Str::orderedUuid(),
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ];

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, $payload)->assertCreated();
    // The offline queue retrying the same create.
    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, $payload)->assertOk();

    expect(AnimalWeight::count())->toBe(1);
});

it('lists readings newest first for the animal', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    foreach ([['2026-05-01', 180], ['2026-06-01', 200]] as [$date, $value]) {
        $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
            'weighable_type' => 'animal',
            'weighable_uuid' => $dam->uuid,
            'measured_on' => $date,
            'entered_value' => $value,
            'entered_unit' => 'kg',
            'sample_size' => 1,
        ])->assertCreated();
    }

    $this->actingAs($user, 'sanctum')
        ->getJson(WEIGHTS_URI."/list/{$dam->uuid}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.measured_on', '2026-06-01');
});

// ─── The weighing chain ──────────────────────────────────────────────────────

it('books the next weighing when a reading is recorded', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => '2026-06-01',
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertCreated();

    $task = weighingTaskFor(AnimalWeight::first());

    expect($task)->not->toBeNull()
        // Dairy Cattle → the livestock category default of 30 days.
        ->and($task->due_date->toDateString())->toBe('2026-07-01')
        ->and($task->task_status)->toBe(Task::STATUS_PENDING)
        ->and($task->priority)->toBe(Task::PRIORITY_MEDIUM)
        ->and($task->title)->toContain('Bella')
        ->and($task->taskable_id)->toBe($dam->id);
});

it('closes the previous weighing task and books a fresh one', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $record = fn (string $date, float $value) => $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => $date,
        'entered_value' => $value,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertCreated();

    $record('2026-05-01', 180);
    $firstTaskId = AnimalWeight::first()->fresh()->next_task_id;

    $record('2026-06-01', 200);

    expect(Task::find($firstTaskId)->task_status)->toBe(Task::STATUS_COMPLETED);

    $second = AnimalWeight::orderByDesc('id')->first();
    expect(weighingTaskFor($second)->task_status)->toBe(Task::STATUS_PENDING)
        ->and(weighingTaskFor($second)->due_date->toDateString())->toBe('2026-07-01')
        // One open weighing task at a time, not a growing pile.
        ->and(Task::where('title', 'LIKE', 'Weigh %')->where('task_status', Task::STATUS_PENDING)->count())->toBe(1);
});

it('leaves a weighing task the farmer already cancelled alone', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $post = fn (string $date) => $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => $date,
        'entered_value' => 190,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertCreated();

    $post('2026-05-01');
    $task = weighingTaskFor(AnimalWeight::first());
    $task->update(['task_status' => Task::STATUS_CANCELLED]);

    $post('2026-06-01');

    expect($task->fresh()->task_status)->toBe(Task::STATUS_CANCELLED);
});

it('shows the weighing task in the animal task list', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertCreated();

    $titles = collect($this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/list/{$dam->uuid}?taskable_type=animal")
        ->assertOk()
        ->json('data'))->pluck('title');

    expect($titles->contains(fn ($t) => str_starts_with($t, 'Weigh ')))->toBeTrue();
});

// ─── Interval resolution ─────────────────────────────────────────────────────

it('prefers the animal interval, then the type, then the category default', function () {
    ['dam' => $dam, 'type' => $type] = birthTestChain();
    $resolver = app(WeighingIntervalResolver::class);

    // Nothing configured → the livestock category default.
    expect($resolver->daysFor($dam->fresh()))->toBe(30);

    $type->update(['weighing_interval_days' => 21]);
    expect($resolver->daysFor($dam->fresh()))->toBe(21);

    $dam->update(['weighing_interval_days' => 10]);
    expect($resolver->daysFor($dam->fresh()))->toBe(10);
});

it('weighs poultry weekly by default', function () {
    $chain = birthTestChain();
    $poultry = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Broilers',
        'category' => 'poultry',
        'tracking_mode' => 'group_only',
    ]);
    $group = weightTestGroup($chain, ['animal_type_id' => $poultry->id]);

    expect(app(WeighingIntervalResolver::class)->daysFor($group))->toBe(7);
});

// ─── Rejections ──────────────────────────────────────────────────────────────

it('rejects a weighing dated in the future', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->addDay()->toDateString(),
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('measured_on');
});

it('rejects a zero or negative weight', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 0,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('entered_value');
});

it('rejects sampling several head of one animal', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 4,
    ])->assertStatus(422)->assertJsonValidationErrors('sample_size');
});

it('rejects a weighing dated before the animal was born', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();
    $dam->update(['date_of_birth' => '2026-01-15']);

    $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => '2026-01-01',
        'entered_value' => 30,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('measured_on');
});

it('refuses to weigh an animal on another farm', function () {
    ['dam' => $dam] = birthTestChain();
    ['user' => $outsider] = birthTestChain();

    $this->actingAs($outsider, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('weighable_uuid');
});

it('hides another farm\'s readings behind a 404', function () {
    ['dam' => $dam] = birthTestChain();
    ['user' => $outsider] = birthTestChain();

    $this->actingAs($outsider, 'sanctum')
        ->getJson(WEIGHTS_URI."/list/{$dam->uuid}")
        ->assertNotFound();
});

// ─── Surfacing the latest reading ────────────────────────────────────────────

it('surfaces the latest weight on the livestock payload', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    foreach ([['2026-05-01', 180], ['2026-06-01', 205.5]] as [$date, $value]) {
        $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
            'weighable_type' => 'animal',
            'weighable_uuid' => $dam->uuid,
            'measured_on' => $date,
            'entered_value' => $value,
            'entered_unit' => 'kg',
            'sample_size' => 1,
        ])->assertCreated();
    }

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/livestocks/{$dam->uuid}")
        ->assertOk()
        ->assertJsonPath('data.latest_weight_kg', 205.5)
        ->assertJsonPath('data.latest_weighed_on', '2026-06-01')
        ->assertJsonPath('data.weighing_interval_days', 30);
});

it('deletes a reading', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $uuid = $this->actingAs($user, 'sanctum')->postJson(WEIGHTS_URI, [
        'weighable_type' => 'animal',
        'weighable_uuid' => $dam->uuid,
        'measured_on' => now()->toDateString(),
        'entered_value' => 200,
        'entered_unit' => 'kg',
        'sample_size' => 1,
    ])->assertCreated()->json('data.uuid');

    $this->actingAs($user, 'sanctum')->deleteJson(WEIGHTS_URI."/{$uuid}")->assertOk();

    $this->assertSoftDeleted('animal_weights', ['uuid' => $uuid]);
});
