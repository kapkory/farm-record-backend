<?php

namespace App\Http\Controllers\Api\v1\Settings\Crops;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Crops\SaveScheduleRequest;
use App\Http\Resources\Settings\Crops\ScheduleResource;
use App\Models\Core\Schedule;
use App\Models\Core\ScheduleActivity;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleController extends Controller
{
    use ApiResponse;

    /**
     * Store a new schedule with its activities.
     */
    public function storeSchedule(SaveScheduleRequest $request): JsonResponse
    {
        try {
            $farmer = $this->resolveFarmer($request);

            if (! $farmer) {
                return $this->errorResponse('No farmer profile found for this user.', 403);
            }

            $data = $request->validated();

            $schedule = DB::transaction(function () use ($data, $farmer, $request) {
                $schedule = Schedule::create([
                    'uuid'      => (string) Str::orderedUuid(),
                    'name'      => $data['name'],
                    'crop_id'   => $data['crop_id'],
                    'farmer_id' => $farmer->id,
                ]);

                $this->syncActivities($schedule, $data['activities'], $request->user()->id);

                return $schedule;
            });

            $schedule->load('activities', 'crop');

            return $this->successResponse(new ScheduleResource($schedule), 'Schedule created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create schedule', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Update an existing schedule and its activities.
     */
    public function updateSchedule(SaveScheduleRequest $request, string $uuid): JsonResponse
    {
        $schedule = Schedule::where('uuid', $uuid)->first();

        if (! $schedule) {
            return $this->errorResponse('Schedule not found', 404);
        }

        try {
            $data = $request->validated();

            DB::transaction(function () use ($schedule, $data, $request) {
                $schedule->update([
                    'name'    => $data['name'],
                    'crop_id' => $data['crop_id'],
                    'status'  => ($data['status'] ?? 'active') === 'active' ? 1 : 0,
                ]);

                $this->syncActivities($schedule, $data['activities'], $request->user()->id);
            });

            $schedule->load('activities', 'crop');

            return $this->successResponse(new ScheduleResource($schedule), 'Schedule updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update schedule', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * List schedules for the authenticated user's farmer, optionally filtered by crop.
     */
    public function listSchedules(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        if ($farmerIds->isEmpty()) {
            return $this->errorResponse('No farmer profile found for this user.', 403);
        }

        $query = Schedule::with(['activities', 'crop:id,name'])
            ->whereIn('farmer_id', $farmerIds);


        if ($request->filled('crop_id')) {
            $query->where('crop_id', $request->query('crop_id'));
        }

        $schedules = $query->orderByDesc('created_at')->get();

        return $this->successResponse(ScheduleResource::collection($schedules), 'Schedules retrieved successfully');
    }

    /**
     * Show a single schedule with its activities.
     */
    public function showSchedule(string $uuid): JsonResponse
    {
        $schedule = Schedule::with(['activities', 'crop:id,name'])
            ->where('uuid', $uuid)
            ->first();

        if (! $schedule) {
            return $this->errorResponse('Schedule not found', 404);
        }

        return $this->successResponse(new ScheduleResource($schedule), 'Schedule retrieved successfully');
    }

    /**
     * Delete a schedule and its activities.
     */
    public function destroySchedule(string $uuid): JsonResponse
    {
        $schedule = Schedule::where('uuid', $uuid)->first();

        if (! $schedule) {
            return $this->errorResponse('Schedule not found', 404);
        }

        try {
            DB::transaction(function () use ($schedule) {
                $schedule->activities()->delete();
                $schedule->delete();
            });

            return $this->successResponse([], 'Schedule deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete schedule', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Sync activities for a schedule: create new, update existing, remove deleted.
     */
    private function syncActivities(Schedule $schedule, array $activities, int $userId): void
    {
        $incomingIds = collect($activities)
            ->pluck('id')
            ->filter()
            ->values();

        // Delete activities that were removed from the list
        $schedule->activities()
            ->whereNotIn('id', $incomingIds)
            ->delete();

        foreach ($activities as $activity) {
            $days = $this->convertToDays($activity['offset_value'], $activity['offset_unit']);

            $attributes = [
                'activity_name'       => $activity['title'],
                'days_since_planting' => $days,
                'priority'            => $activity['priority'],
                'notes'               => $activity['description'] ?? null,
                'user_id'             => $userId,
            ];

            if (! empty($activity['id'])) {
                // Update existing activity
                $schedule->activities()->where('id', $activity['id'])->update($attributes);
            } else {
                // Create new activity
                $schedule->activities()->create([
                    'uuid' => (string) Str::orderedUuid(),
                    ...$attributes,
                ]);
            }
        }
    }

    /**
     * Convert offset value + unit to days.
     */
    private function convertToDays(int $value, string $unit): int
    {
        return match ($unit) {
            'weeks'  => $value * 7,
            'months' => $value * 30,
            default  => $value,
        };
    }

    /**
     * Resolve the farmer for the authenticated user.
     */
    private function resolveFarmer(Request $request)
    {
        return $request->user()->farmers()->wherePivot('role', 'owner')->first()
            ?? $request->user()->farmers()->first();
    }
}
