<?php

namespace App\Services\Billing;

use App\Models\Core\Farmer;
use App\Models\Core\Plan;
use App\Models\Core\Subscription;
use App\Models\Core\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Subscription lifecycle for the manual-activation billing model: farmers
 * pick a plan (starting a trial), and a superadmin records payments that
 * extend the paid-through date and keep the subscription active. No live
 * payment gateway — every money movement is entered by an administrator.
 */
class SubscriptionService
{
    /** Farmer chooses a plan. Starts (or restarts) a trial if never paid. */
    public function subscribe(Farmer $farmer, Plan $plan): Subscription
    {
        $subscription = $farmer->subscription;

        if (! $subscription) {
            return Subscription::create([
                'uuid' => (string) Str::orderedUuid(),
                'farmer_id' => $farmer->id,
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_TRIALING,
                'started_at' => now(),
                'trial_ends_at' => now()->addDays($plan->trial_days),
            ]);
        }

        // Switching plans: keep any paid-through time, just point at the new plan.
        $subscription->plan_id = $plan->id;
        if ($subscription->status === Subscription::STATUS_CANCELED || $subscription->status === Subscription::STATUS_EXPIRED) {
            $subscription->status = Subscription::STATUS_TRIALING;
            $subscription->trial_ends_at = now()->addDays($plan->trial_days);
            $subscription->canceled_at = null;
        }
        $subscription->save();

        return $subscription->refresh();
    }

    /** Superadmin assigns a plan to a farmer (creating a subscription if needed). */
    public function assignPlan(Farmer $farmer, Plan $plan): Subscription
    {
        return $this->subscribe($farmer, $plan);
    }

    /**
     * Record a manual payment. Extends the paid-through date by the plan's
     * period (from the later of now or the current period end) and marks the
     * subscription active.
     */
    public function recordPayment(Subscription $subscription, User $recordedBy, array $data): SubscriptionPayment
    {
        $plan = $subscription->plan;
        $periodDays = $plan?->periodDays() ?? 30;

        $base = $subscription->current_period_end && $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end->copy()
            : now();
        $newPeriodEnd = $base->addDays($periodDays);

        $paidAt = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

        $payment = SubscriptionPayment::create([
            'uuid' => (string) Str::orderedUuid(),
            'subscription_id' => $subscription->id,
            'farmer_id' => $subscription->farmer_id,
            'amount' => $data['amount'] ?? $plan?->price ?? 0,
            'currency' => $data['currency'] ?? $plan?->currency ?? 'KES',
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'period_start' => now()->toDateString(),
            'period_end' => $newPeriodEnd->toDateString(),
            'paid_at' => $paidAt,
            'recorded_by_user_id' => $recordedBy->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => $subscription->started_at ?? now(),
            'current_period_end' => $newPeriodEnd,
            'canceled_at' => null,
        ]);

        return $payment;
    }

    /** Superadmin directly sets status / paid-through (manual override). */
    public function updateStatus(Subscription $subscription, string $status, ?Carbon $periodEnd = null): Subscription
    {
        $subscription->status = $status;

        if ($status === Subscription::STATUS_CANCELED) {
            $subscription->canceled_at = now();
        }

        if ($periodEnd) {
            $subscription->current_period_end = $periodEnd;
        }

        $subscription->save();

        return $subscription;
    }

    /** Cancel — access remains until the paid-through date, then it expires. */
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => Subscription::STATUS_CANCELED,
            'canceled_at' => now(),
        ]);

        return $subscription;
    }
}
