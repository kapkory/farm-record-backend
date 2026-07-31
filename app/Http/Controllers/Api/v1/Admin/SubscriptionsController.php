<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\RecordSubscriptionPaymentRequest;
use App\Http\Resources\Billing\SubscriptionPaymentResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Core\Farmer;
use App\Models\Core\Plan;
use App\Models\Core\Subscription;
use App\Models\Core\SubscriptionPayment;
use App\Services\Billing\SubscriptionService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionsController extends Controller
{
    use ApiResponse;

    public function __construct(protected SubscriptionService $subscriptions) {}

    /** Every farmer's subscription, filterable by status / plan / search. */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['plan', 'farmer'])
            ->join('farmers', 'farmers.id', '=', 'subscriptions.farmer_id')
            ->select('subscriptions.*')
            ->orderBy('farmers.display_name');

        if ($request->filled('status')) {
            $query->where('subscriptions.status', $request->input('status'));
        }

        if ($request->filled('plan_uuid')) {
            $planId = Plan::where('uuid', $request->input('plan_uuid'))->value('id');
            $query->where('subscriptions.plan_id', $planId);
        }

        if ($request->filled('search')) {
            $query->where('farmers.display_name', 'like', '%'.$request->input('search').'%');
        }

        return $this->successResponse(
            SubscriptionResource::collection($query->limit(500)->get()),
            'Subscriptions retrieved successfully'
        );
    }

    /** Headline numbers for the superadmin billing dashboard. */
    public function stats(): JsonResponse
    {
        $byStatus = Subscription::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        // Monthly recurring revenue: active subscriptions' plan prices,
        // normalised to a monthly figure.
        $mrr = Subscription::where('subscriptions.status', Subscription::STATUS_ACTIVE)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw("SUM(CASE WHEN plans.interval = 'yearly' THEN plans.price / 12 ELSE plans.price END) as mrr")
            ->value('mrr');

        $collectedThisMonth = SubscriptionPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return $this->successResponse([
            'total' => Subscription::count(),
            'trialing' => (int) ($byStatus[Subscription::STATUS_TRIALING] ?? 0),
            'active' => (int) ($byStatus[Subscription::STATUS_ACTIVE] ?? 0),
            'past_due' => (int) ($byStatus[Subscription::STATUS_PAST_DUE] ?? 0),
            'expired' => (int) ($byStatus[Subscription::STATUS_EXPIRED] ?? 0),
            'canceled' => (int) ($byStatus[Subscription::STATUS_CANCELED] ?? 0),
            'mrr' => (float) ($mrr ?? 0),
            'collected_this_month' => (float) $collectedThisMonth,
        ], 'Subscription stats retrieved successfully');
    }

    public function show(string $uuid): JsonResponse
    {
        $subscription = Subscription::with(['plan', 'farmer', 'payments' => fn ($q) => $q->with('recordedBy')->latest('paid_at')])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->successResponse(new SubscriptionResource($subscription), 'Subscription retrieved successfully');
    }

    /** Assign / switch a farmer's plan. */
    public function assign(Request $request, string $farmerUuid): JsonResponse
    {
        $validated = $request->validate([
            'plan_uuid' => ['required', 'uuid', 'exists:plans,uuid'],
        ]);

        $farmer = Farmer::where('uuid', $farmerUuid)->firstOrFail();
        $plan = Plan::where('uuid', $validated['plan_uuid'])->firstOrFail();

        $subscription = $this->subscriptions->assignPlan($farmer, $plan);

        return $this->successResponse(
            new SubscriptionResource($subscription->load('plan', 'farmer')),
            'Plan assigned successfully'
        );
    }

    public function recordPayment(RecordSubscriptionPaymentRequest $request, string $uuid): JsonResponse
    {
        $subscription = Subscription::with('plan')->where('uuid', $uuid)->firstOrFail();

        $payment = $this->subscriptions->recordPayment($subscription, $request->user(), $request->validated());

        return $this->successResponse([
            'payment' => new SubscriptionPaymentResource($payment),
            'subscription' => new SubscriptionResource($subscription->fresh(['plan', 'farmer', 'payments'])),
        ], 'Payment recorded successfully', 201);
    }

    /** Manual status override (activate/past_due/expired) with optional period end. */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:trialing,active,past_due,expired,canceled'],
            'current_period_end' => ['nullable', 'date'],
        ]);

        $subscription = Subscription::where('uuid', $uuid)->firstOrFail();

        $this->subscriptions->updateStatus(
            $subscription,
            $validated['status'],
            ! empty($validated['current_period_end']) ? Carbon::parse($validated['current_period_end']) : null,
        );

        return $this->successResponse(
            new SubscriptionResource($subscription->fresh(['plan', 'farmer'])),
            'Subscription updated successfully'
        );
    }

    public function cancel(string $uuid): JsonResponse
    {
        $subscription = Subscription::where('uuid', $uuid)->firstOrFail();
        $this->subscriptions->cancel($subscription);

        return $this->successResponse(
            new SubscriptionResource($subscription->fresh(['plan', 'farmer'])),
            'Subscription canceled successfully'
        );
    }
}
