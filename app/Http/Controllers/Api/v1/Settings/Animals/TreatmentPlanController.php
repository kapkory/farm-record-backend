<?php

namespace App\Http\Controllers\Api\v1\Settings\Animals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Animals\SaveTreatmentPlanRequest;
use App\Http\Resources\Settings\Animals\TreatmentPlanResource;
use App\Models\Core\TreatmentPlan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TreatmentPlanController extends Controller
{
    use ApiResponse;

    /**
     * Store a new treatment plan with its activities.
     */
    public function storeTreatmentPlan(SaveTreatmentPlanRequest $request): JsonResponse
    {
        try {
            $farmer = $this->resolveFarmer($request);

            if (! $farmer) {
                return $this->errorResponse('No farmer profile found for this user.', 403);
            }

            $data = $request->validated();

            $plan = DB::transaction(function () use ($data, $farmer, $request) {
                $plan = TreatmentPlan::create([
                    'uuid' => (string) Str::orderedUuid(),
                    'name' => $data['name'],
                    'animal_type_id' => $data['animal_type_id'] ?? null,
                    'farmer_id' => $farmer->id,
                    'status' => ($data['status'] ?? 'active') === 'active' ? 1 : 0,
                ]);

                $this->syncActivities($plan, $data['activities'], $request->user()->id);

                return $plan;
            });

            $plan->load('activities', 'animalType:id,name');

            return $this->successResponse(new TreatmentPlanResource($plan), 'Treatment plan created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create treatment plan', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Update an existing treatment plan and its activities.
     */
    public function updateTreatmentPlan(SaveTreatmentPlanRequest $request, string $uuid): JsonResponse
    {
        $plan = $this->findOwnPlan($request, $uuid);

        if (! $plan) {
            return $this->errorResponse('Treatment plan not found', 404);
        }

        if ($plan->is_system) {
            return $this->errorResponse('Default plans cannot be edited. Create your own version instead.', 403);
        }

        try {
            $data = $request->validated();

            DB::transaction(function () use ($plan, $data, $request) {
                $plan->update([
                    'name' => $data['name'],
                    'animal_type_id' => $data['animal_type_id'] ?? null,
                    'status' => ($data['status'] ?? 'active') === 'active' ? 1 : 0,
                ]);

                $this->syncActivities($plan, $data['activities'], $request->user()->id);
            });

            $plan->load('activities', 'animalType:id,name');

            return $this->successResponse(new TreatmentPlanResource($plan), 'Treatment plan updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update treatment plan', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Delete a treatment plan and its activities. Flocks/animals keep their
     * already-generated tasks; their plan link is nulled by the FK.
     */
    public function destroyTreatmentPlan(Request $request, string $uuid): JsonResponse
    {
        $plan = $this->findOwnPlan($request, $uuid);

        if (! $plan) {
            return $this->errorResponse('Treatment plan not found', 404);
        }

        if ($plan->is_system) {
            return $this->errorResponse('Default plans cannot be deleted.', 403);
        }

        try {
            DB::transaction(function () use ($plan) {
                $plan->activities()->delete();
                $plan->delete();
            });

            return $this->successResponse([], 'Treatment plan deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete treatment plan', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * List the farmer's own treatment plans plus the global system templates
     * (e.g. the default Layers Chicken Vaccination Schedule), optionally
     * filtered by animal type.
     */
    public function listTreatmentPlans(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        $query = TreatmentPlan::with(['activities', 'animalType:id,name'])
            ->where(function ($q) use ($farmerIds) {
                $q->whereIn('farmer_id', $farmerIds)
                    ->orWhere('is_system', true);
            });

        if ($request->filled('animal_type_id')) {
            $query->where('animal_type_id', $request->query('animal_type_id'));
        }

        $plans = $query->orderByDesc('is_system')->orderByDesc('created_at')->get();

        return $this->successResponse(TreatmentPlanResource::collection($plans), 'Treatment plans retrieved successfully');
    }

    /**
     * Show a single treatment plan with its activities.
     */
    public function showTreatmentPlan(string $uuid): JsonResponse
    {
        $plan = TreatmentPlan::with(['activities', 'animalType:id,name'])
            ->where('uuid', $uuid)
            ->first();

        if (! $plan) {
            return $this->errorResponse('Treatment plan not found', 404);
        }

        return $this->successResponse(new TreatmentPlanResource($plan), 'Treatment plan retrieved successfully');
    }

    /**
     * Sync activities for a plan: create new, update existing, remove deleted.
     */
    private function syncActivities(TreatmentPlan $plan, array $activities, int $userId): void
    {
        $incomingIds = collect($activities)
            ->pluck('id')
            ->filter()
            ->values();

        $plan->activities()
            ->whereNotIn('id', $incomingIds)
            ->delete();

        foreach ($activities as $activity) {
            $attributes = [
                'vaccine' => $activity['vaccine'],
                'disease' => $activity['disease'] ?? null,
                'route' => $activity['route'] ?? null,
                'age_days' => $this->convertToDays($activity['offset_value'], $activity['offset_unit']),
                'priority' => $activity['priority'],
                'notes' => $activity['notes'] ?? null,
                'user_id' => $userId,
            ];

            if (! empty($activity['id'])) {
                $plan->activities()->where('id', $activity['id'])->update($attributes);
            } else {
                $plan->activities()->create([
                    'uuid' => (string) Str::orderedUuid(),
                    ...$attributes,
                ]);
            }
        }
    }

    private function convertToDays(int $value, string $unit): int
    {
        return match ($unit) {
            'weeks' => $value * 7,
            'months' => $value * 30,
            default => $value,
        };
    }

    /**
     * The user's own (non-deleted) plan by uuid; system plans and other
     * farmers' plans are not editable, so treat them as not found here
     * (except is_system, which callers turn into a clearer 403).
     */
    private function findOwnPlan(Request $request, string $uuid): ?TreatmentPlan
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        return TreatmentPlan::where('uuid', $uuid)
            ->where(function ($q) use ($farmerIds) {
                $q->whereIn('farmer_id', $farmerIds)->orWhere('is_system', true);
            })
            ->first();
    }

    private function resolveFarmer(Request $request)
    {
        return $request->user()->farmers()->wherePivot('role', 'owner')->first()
            ?? $request->user()->farmers()->first();
    }
}
