<?php

use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Planting;
use App\Models\Core\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const TASKS_URI = '/api/v1/tasks';

function taskUser(): array
{
    $user = User::factory()->create();
    $farmer = Farmer::create(['uuid' => (string) Str::orderedUuid(), 'display_name' => 'Task Farmer', 'type' => 'individual', 'status' => 1]);
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 1]);
    $farm = Farm::create(['uuid' => (string) Str::orderedUuid(), 'farmer_id' => $farmer->id, 'name' => 'Task Farm', 'location' => 'Nakuru', 'type' => 'crop', 'ownership_type' => 'owned', 'status' => 1]);
    $crop = Crop::create(['uuid' => (string) Str::orderedUuid(), 'name' => 'Maize', 'description' => 'test']);
    $planting = Planting::create(['uuid' => (string) Str::orderedUuid(), 'farm_id' => $farm->id, 'crop_id' => $crop->id, 'date_planted' => '2026-03-01', 'purpose' => 'commercial', 'user_id' => $user->id]);

    return [$user, $farm, $planting];
}

it('creates a standalone task with integer status and priority', function () {
    [$user] = taskUser();

    $response = $this->actingAs($user, 'sanctum')->postJson(TASKS_URI, [
        'title'    => 'Apply fertiliser',
        'due_date' => '2026-04-01',
        'priority' => Task::PRIORITY_HIGH,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Apply fertiliser')
        ->assertJsonPath('data.priority', Task::PRIORITY_HIGH)
        ->assertJsonPath('data.priority_label', 'high')
        ->assertJsonPath('data.task_status', Task::STATUS_PENDING)
        ->assertJsonPath('data.task_status_label', 'pending');

    $this->assertDatabaseHas('tasks', [
        'title'       => 'Apply fertiliser',
        'user_id'     => $user->id,
        'priority'    => Task::PRIORITY_HIGH,
        'task_status' => Task::STATUS_PENDING,
    ]);
});

it('creates a task linked to a planting via taskable', function () {
    [$user, $farm, $planting] = taskUser();

    $response = $this->actingAs($user, 'sanctum')->postJson(TASKS_URI, [
        'title'         => 'Monitor pest pressure',
        'due_date'      => '2026-04-10',
        'priority'      => Task::PRIORITY_MEDIUM,
        'taskable_type' => 'planting',
        'taskable_uuid' => $planting->uuid,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.taskable_type', 'Planting')
        ->assertJsonPath('data.task_status', Task::STATUS_PENDING);

    $this->assertDatabaseHas('tasks', [
        'taskable_type' => App\Models\Core\Planting::class,
        'taskable_id'   => $planting->id,
    ]);
});

it('lists only tasks the authenticated user created or was assigned to', function () {
    [$user] = taskUser();
    $other = User::factory()->create();

    Task::create(['uuid' => (string) Str::orderedUuid(), 'title' => 'My task', 'user_id' => $user->id, 'priority' => Task::PRIORITY_MEDIUM, 'task_status' => Task::STATUS_PENDING]);
    Task::create(['uuid' => (string) Str::orderedUuid(), 'title' => 'Assigned to me', 'user_id' => $other->id, 'assigned_to_user_id' => $user->id, 'priority' => Task::PRIORITY_MEDIUM, 'task_status' => Task::STATUS_PENDING]);
    Task::create(['uuid' => (string) Str::orderedUuid(), 'title' => 'Not mine', 'user_id' => $other->id, 'priority' => Task::PRIORITY_MEDIUM, 'task_status' => Task::STATUS_PENDING]);

    $response = $this->actingAs($user, 'sanctum')->getJson(TASKS_URI.'/list');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data');

    $titles = collect($response->json('data'))->pluck('title')->sort()->values()->all();
    expect($titles)->toBe(['Assigned to me', 'My task']);
});

it('updates task status using an integer', function () {
    [$user] = taskUser();

    $task = Task::create([
        'uuid'        => (string) Str::orderedUuid(),
        'title'       => 'Harvest maize',
        'user_id'     => $user->id,
        'priority'    => Task::PRIORITY_MEDIUM,
        'task_status' => Task::STATUS_IN_PROGRESS,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson(TASKS_URI.'/'.$task->uuid.'/status', ['task_status' => Task::STATUS_COMPLETED])
        ->assertOk()
        ->assertJsonPath('data.task_status', Task::STATUS_COMPLETED)
        ->assertJsonPath('data.task_status_label', 'completed');

    $this->assertDatabaseHas('tasks', ['uuid' => $task->uuid, 'task_status' => Task::STATUS_COMPLETED]);
});

it('rejects an invalid integer status', function () {
    [$user] = taskUser();

    $task = Task::create([
        'uuid'        => (string) Str::orderedUuid(),
        'title'       => 'Bad status',
        'user_id'     => $user->id,
        'priority'    => Task::PRIORITY_LOW,
        'task_status' => Task::STATUS_PENDING,
    ]);

    $this->actingAs($user, 'sanctum')
        ->patchJson(TASKS_URI.'/'.$task->uuid.'/status', ['task_status' => 99])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['task_status']);
});

it('soft deletes a task', function () {
    [$user] = taskUser();

    $task = Task::create([
        'uuid'        => (string) Str::orderedUuid(),
        'title'       => 'Delete me',
        'user_id'     => $user->id,
        'priority'    => Task::PRIORITY_LOW,
        'task_status' => Task::STATUS_PENDING,
    ]);

    $this->actingAs($user, 'sanctum')->deleteJson(TASKS_URI.'/'.$task->uuid)->assertOk();

    $this->assertSoftDeleted('tasks', ['uuid' => $task->uuid]);
});

it('validates required fields on task creation', function () {
    [$user] = taskUser();

    $this->actingAs($user, 'sanctum')
        ->postJson(TASKS_URI, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});
