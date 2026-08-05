<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Tasks recorded against a given animal, however they were created. */
function tasksForAnimal(Animal $animal)
{
    return Task::where('taskable_type', $animal::class)
        ->where('taskable_id', $animal->id)
        ->get();
}

it('creates one high-priority task on the dam due on the expected birth date', function () {
    ['dam' => $dam, 'breeding' => $breeding] = birthTestChain();

    $breeding->refresh();
    expect($breeding->expected_birth_date)->not->toBeNull()
        ->and($breeding->birth_task_id)->not->toBeNull();

    $tasks = tasksForAnimal($dam);
    expect($tasks)->toHaveCount(1);

    $task = $tasks->first();
    expect($task->due_date->toDateString())->toBe($breeding->expected_birth_date->toDateString())
        ->and($task->priority)->toBe(Task::PRIORITY_HIGH)
        ->and($task->task_status)->toBe(Task::STATUS_PENDING)
        ->and($task->title)->toContain('Bella');
});

it('shows the birthing task in the animal task list', function () {
    ['user' => $user, 'dam' => $dam] = birthTestChain();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tasks/list/{$dam->uuid}?taskable_type=animal")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('re-dates the existing task instead of creating a second one', function () {
    ['breeding' => $breeding, 'dam' => $dam] = birthTestChain();

    $taskId = $breeding->fresh()->birth_task_id;

    $breeding->update(['service_date' => now()->subDays(200)->toDateString()]);
    $breeding->refresh();

    expect(tasksForAnimal($dam))->toHaveCount(1)
        ->and($breeding->birth_task_id)->toBe($taskId)
        ->and(Task::find($taskId)->due_date->toDateString())
        ->toBe($breeding->expected_birth_date->toDateString());
});

it('follows a manually edited expected birth date', function () {
    ['breeding' => $breeding] = birthTestChain();

    $newDate = now()->addDays(21)->toDateString();
    $breeding->update(['expected_birth_date' => $newDate]);

    expect(Task::find($breeding->fresh()->birth_task_id)->due_date->toDateString())->toBe($newDate);
});

it('cancels the task when the pregnancy is aborted', function () {
    ['breeding' => $breeding] = birthTestChain();

    $taskId = $breeding->fresh()->birth_task_id;
    $breeding->update(['status' => 'aborted']);

    expect(Task::find($taskId)->task_status)->toBe(Task::STATUS_CANCELLED);
});

it('cancels the task when the breeding failed', function () {
    ['breeding' => $breeding] = birthTestChain();

    $taskId = $breeding->fresh()->birth_task_id;
    $breeding->update(['status' => 'failed']);

    expect(Task::find($taskId)->task_status)->toBe(Task::STATUS_CANCELLED);
});

it('leaves a task the farmer already closed alone', function () {
    ['breeding' => $breeding] = birthTestChain();

    $task = Task::find($breeding->fresh()->birth_task_id);
    $task->update(['task_status' => Task::STATUS_CANCELLED]);

    $breeding->update(['service_date' => now()->subDays(100)->toDateString()]);

    expect($task->fresh()->task_status)->toBe(Task::STATUS_CANCELLED);
});

it('creates no task when the gestation length cannot be worked out', function () {
    // No breed gestation and a species name the estimator has no entry for.
    ['dam' => $dam, 'farm' => $farm, 'user' => $user, 'type' => $type] = birthTestChain([
        'gestation_days' => null,
        'type_name' => 'Alpaca',
    ]);

    $breeding = AnimalBreeding::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'dam_id' => $dam->id,
        'sire_type' => 'ai',
        'ai_bull_name' => 'Unknown',
        'service_date' => now()->subDays(30)->toDateString(),
        'status' => 'pending',
        'user_id' => $user->id,
    ]);

    expect($breeding->fresh()->expected_birth_date)->toBeNull()
        ->and($breeding->fresh()->birth_task_id)->toBeNull();
});
