<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePlanRequest;
use App\Http\Resources\Billing\PlanResource;
use App\Models\Core\Plan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PlansController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $plans = Plan::withCount('subscriptions')->orderBy('sort_order')->orderBy('price')->get();

        return $this->successResponse(PlanResource::collection($plans), 'Plans retrieved successfully');
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['uuid'] = (string) Str::orderedUuid();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['currency'] = $data['currency'] ?? 'KES';

        $plan = Plan::create($data);

        return $this->successResponse(new PlanResource($plan), 'Plan created successfully', 201);
    }

    public function update(StorePlanRequest $request, string $uuid): JsonResponse
    {
        $plan = Plan::where('uuid', $uuid)->firstOrFail();
        $data = $request->validated();

        if (empty($data['slug'])) {
            unset($data['slug']);
        }

        $plan->update($data);

        return $this->successResponse(new PlanResource($plan), 'Plan updated successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $plan = Plan::withCount('subscriptions')->where('uuid', $uuid)->firstOrFail();

        if ($plan->subscriptions_count > 0) {
            // Don't orphan subscribers — archive instead of deleting.
            $plan->update(['is_active' => false]);

            return $this->successResponse(new PlanResource($plan), 'Plan has subscribers, so it was archived instead of deleted');
        }

        $plan->delete();

        return $this->successResponse(null, 'Plan deleted successfully');
    }
}
