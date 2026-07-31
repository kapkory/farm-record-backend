<?php

namespace App\Http\Controllers\Api\v1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\PlanResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Core\Plan;
use App\Services\Billing\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Farmer-facing billing: view active plans, see your own subscription, and
 * choose a plan (which starts a trial). Payments are recorded by a superadmin.
 */
class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(protected SubscriptionService $subscriptions) {}

    /** Active plans for the subscribe screen. */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get();

        return $this->successResponse(PlanResource::collection($plans), 'Plans retrieved successfully');
    }

    /** The authenticated user's farmer subscription (with plan + payments). */
    public function show(Request $request): JsonResponse
    {
        $farmer = $request->user()->farmers()->first();

        if (! $farmer) {
            return $this->errorResponse('No farmer profile is linked to your account.', 422);
        }

        $subscription = $farmer->subscription()->with(['plan', 'payments' => fn ($q) => $q->latest('paid_at')])->first();

        if (! $subscription) {
            return $this->successResponse(null, 'No subscription yet');
        }

        return $this->successResponse(new SubscriptionResource($subscription), 'Subscription retrieved successfully');
    }

    /** Farmer chooses a plan — starts a trial (or switches plans). */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_uuid' => ['required', 'uuid', 'exists:plans,uuid'],
        ]);

        $farmer = $request->user()->farmers()->first();

        if (! $farmer) {
            return $this->errorResponse('No farmer profile is linked to your account.', 422);
        }

        $plan = Plan::where('uuid', $validated['plan_uuid'])->where('is_active', true)->firstOrFail();
        $subscription = $this->subscriptions->subscribe($farmer, $plan);

        return $this->successResponse(
            new SubscriptionResource($subscription->load('plan')),
            'Subscription updated successfully'
        );
    }
}
