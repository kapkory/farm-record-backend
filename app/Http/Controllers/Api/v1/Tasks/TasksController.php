<?php

namespace App\Http\Controllers\Api\v1\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Http\Resources\Tasks\TaskCalendarResource;
use App\Http\Resources\Tasks\TaskResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use App\Models\Core\Task;
use App\Models\Core\Treatment;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TasksController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    // ─── Resolve taskable model from short type + uuid ────────────────────────
    protected function resolveTaskable(string $type, string $uuid): ?object
    {
        return match ($type) {
            'planting' => Planting::where('uuid', $uuid)->firstOrFail(),
            'farm' => Farm::where('uuid', $uuid)->firstOrFail(),
            'treatment' => Treatment::where('uuid', $uuid)->firstOrFail(),
            'animal_group' => AnimalGroup::where('uuid', $uuid)->firstOrFail(),
            'animal' => Animal::where('uuid', $uuid)->firstOrFail(),
            'hive' => Hive::where('uuid', $uuid)->firstOrFail(),
            default => null,
        };
    }

    // ─── List tasks (global or scoped to a taskable) ──────────────────────────
    // URL pattern: /list/{taskable_uuid}?taskable_type=planting
    public function listTasks(Request $request, ?string $taskable_uuid = null): JsonResponse
    {
        $query = Task::query()
            ->with(['creator', 'assignee', 'subTasks'])
            ->withCount('subTasks')
            ->forUser($request->user()->id);

        // Resolve taskable from route param (uuid) + query param (type)
        $taskableType = $request->query('taskable_type');

        if ($taskable_uuid && $taskableType) {
            $taskable = $this->resolveTaskable($taskableType, $taskable_uuid);
            if ($taskable) {
                $query->forTaskable($taskable::class, $taskable->id);
            }
        }

        // Optional filters via query string
        if ($request->filled('task_status')) {
            $query->where('task_status', $request->input('task_status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // top-level tasks only unless caller asks for subtasks
        if (! $request->boolean('include_subtasks', false)) {
            $query->whereNull('parent_task_id');
        }

        $tasks = $query->orderByRaw("CASE task_status
                WHEN 'pending'     THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'on_hold'     THEN 3
                WHEN 'completed'   THEN 4
                WHEN 'cancelled'   THEN 5
                ELSE 6 END")
            ->orderBy('due_date')
            ->orderByDesc('priority')
            ->get();

        return $this->successResponse(
            TaskResource::collection($tasks),
            'Tasks retrieved successfully'
        );
    }

    // ─── Store a new task ─────────────────────────────────────────────────────
    public function storeTask(StoreTaskRequest $request): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Task::class,
            fn (Task $task) => $task->user_id === $request->user()->id
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new TaskResource($existing->load(['creator', 'assignee', 'subTasks'])),
                'Task already saved'
            );
        }

        try {
            $validated = $request->validated();
            $taskableType = null;
            $taskableId = null;

            if (! empty($validated['taskable_type']) && ! empty($validated['taskable_uuid'])) {
                $taskable = $this->resolveTaskable($validated['taskable_type'], $validated['taskable_uuid']);
                $taskableType = $taskable::class;
                $taskableId = $taskable->id;
            }

            $task = Task::create([
                'uuid' => $uuid,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'user_id' => $request->user()->id,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'priority' => $validated['priority'] ?? Task::PRIORITY_MEDIUM,
                'task_status' => $validated['task_status'] ?? Task::STATUS_PENDING,
                'parent_task_id' => $validated['parent_task_id'] ?? null,
                'taskable_type' => $taskableType,
                'taskable_id' => $taskableId,
            ])->load(['creator', 'assignee', 'subTasks']);

            return $this->successResponse(new TaskResource($task), 'Task created successfully', 201);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Task::class, $uuid)) {
                return $this->successResponse(
                    new TaskResource($replayed->load(['creator', 'assignee', 'subTasks'])),
                    'Task already saved'
                );
            }

            return $this->errorResponse('Failed to create task', 500, ['exception' => $e->getMessage()]);
        }
    }

    // ─── Show single task with subtasks ──────────────────────────────────────
    public function showTask(string $uuid): JsonResponse
    {
        $task = Task::with(['creator', 'assignee', 'subTasks.assignee', 'parentTask'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->successResponse(new TaskResource($task), 'Task retrieved successfully');
    }

    // ─── Update task status ───────────────────────────────────────────────────
    public function updateStatus(UpdateTaskStatusRequest $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();

        try {
            $task->update(['task_status' => $request->validated('task_status')]);

            return $this->successResponse(
                new TaskResource($task->load(['creator', 'assignee'])),
                'Task status updated successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update task status', 500, ['exception' => $e->getMessage()]);
        }
    }

    // ─── Update full task ─────────────────────────────────────────────────────
    public function updateTask(StoreTaskRequest $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();

        try {
            $validated = $request->validated();
            $taskableType = $task->taskable_type;
            $taskableId = $task->taskable_id;

            if (! empty($validated['taskable_type']) && ! empty($validated['taskable_uuid'])) {
                $taskable = $this->resolveTaskable($validated['taskable_type'], $validated['taskable_uuid']);
                $taskableType = $taskable::class;
                $taskableId = $taskable->id;
            }

            $task->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? $task->description,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? $task->assigned_to_user_id,
                'due_date' => $validated['due_date'] ?? $task->due_date,
                'priority' => $validated['priority'] ?? $task->priority,
                'task_status' => $validated['task_status'] ?? $task->task_status,
                'parent_task_id' => $validated['parent_task_id'] ?? $task->parent_task_id,
                'taskable_type' => $taskableType,
                'taskable_id' => $taskableId,
            ]);

            return $this->successResponse(
                new TaskResource($task->load(['creator', 'assignee', 'subTasks'])),
                'Task updated successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update task', 500, ['exception' => $e->getMessage()]);
        }
    }

    // ─── Calendar view — slim response scoped to a date range ────────────────
    // GET /calendar?from=2026-04-01&to=2026-04-30
    public function calendarTasks(Request $request): JsonResponse
    {
        $query = Task::query()
            ->with(['assignee'])
            ->forUser($request->user()->id)
            ->whereNull('parent_task_id');   // top-level tasks only

        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->input('to'));
        }

        if ($request->filled('task_status')) {
            $query->where('task_status', $request->input('task_status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $tasks = $query->orderBy('due_date')->get();

        return $this->successResponse(
            TaskCalendarResource::collection($tasks),
            'Calendar tasks retrieved successfully'
        );
    }

    // ─── Soft delete ─────────────────────────────────────────────────────────
    public function deleteTask(string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();

        try {
            $task->delete();

            return $this->successResponse(null, 'Task deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete task', 500, ['exception' => $e->getMessage()]);
        }
    }
}
