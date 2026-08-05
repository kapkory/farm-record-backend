<?php

namespace App\Services\Inputs;

use App\Models\Core\InputApplication;

/**
 * Splits the cost of one input application across the animals and groups it
 * covered.
 *
 * Pure arithmetic — no models are loaded and nothing is written, so the rules
 * can be tested on their own. Callers pass plain rows and get shares back.
 *
 * The invariant that matters: the shares always add up to exactly the amount
 * being split. Naive division loses cents (1,000 across 3 is 333.33 three times
 * over, which is 999.99), and a cent lost on every application quietly corrupts
 * every cost report built on top of it. The remainder is therefore pushed onto
 * the largest share, where it is proportionally least visible.
 */
class CostAllocator
{
    /**
     * @param  array<int, array{head_count?: int, basis_value?: float|null, manual_cost?: float|null}>  $targets
     * @return array<int, array{head_count: int, basis_value: float|null, allocated_cost: float}>
     */
    public function allocate(float $totalCost, array $targets, string $basis): array
    {
        if ($targets === []) {
            return [];
        }

        $weights = match ($basis) {
            InputApplication::BASIS_MANUAL => null,
            InputApplication::BASIS_BY_WEIGHT => $this->weightBasis($targets),
            default => $this->headBasis($targets),
        };

        // Manual: the farmer decided the split; validation has already checked
        // that the shares add up.
        if ($weights === null) {
            return array_map(fn (array $t) => [
                'head_count' => max(1, (int) ($t['head_count'] ?? 1)),
                'basis_value' => $t['basis_value'] ?? null,
                'allocated_cost' => round((float) ($t['manual_cost'] ?? 0), 2),
            ], $targets);
        }

        return $this->spread($totalCost, $targets, $weights);
    }

    /** Every head carries the same share — right for dip, vaccines and drugs. */
    private function headBasis(array $targets): array
    {
        return array_map(fn (array $t) => (float) max(1, (int) ($t['head_count'] ?? 1)), $targets);
    }

    /**
     * Weighted by live weight × head — right for feed, where a 400 kg cow eats
     * more than a calf.
     *
     * An animal that has never been weighed is charged at the average weight of
     * those that have, not at its head count. Using the head count would give it
     * a weight of 1 against a neighbour's 400 and hand it well under a percent
     * of the bill, which is a worse answer than simply assuming it is typical.
     * When nothing at all has been weighed, the split degrades to per head.
     */
    private function weightBasis(array $targets): array
    {
        $known = [];
        foreach ($targets as $t) {
            $weight = $t['basis_value'] ?? null;
            if ($weight && $weight > 0) {
                $known[] = (float) $weight;
            }
        }

        $fallback = $known === [] ? null : array_sum($known) / count($known);

        return array_map(function (array $t) use ($fallback) {
            $head = max(1, (int) ($t['head_count'] ?? 1));
            $weight = $t['basis_value'] ?? null;
            $effective = $weight && $weight > 0 ? (float) $weight : $fallback;

            // Nothing weighed anywhere — this is a per-head split in all but name.
            return $effective === null ? (float) $head : $effective * $head;
        }, $targets);
    }

    /**
     * Distribute proportionally to $weights, then settle the rounding remainder
     * on the largest share so the total is exact to the cent.
     */
    private function spread(float $totalCost, array $targets, array $weights): array
    {
        $totalWeight = array_sum($weights);
        $totalCents = (int) round($totalCost * 100);

        // Degenerate input (all weights zero): fall back to an even split rather
        // than dividing by zero.
        if ($totalWeight <= 0) {
            $weights = array_fill(0, count($targets), 1.0);
            $totalWeight = (float) count($targets);
        }

        $allocatedCents = [];
        foreach ($targets as $i => $target) {
            $allocatedCents[$i] = (int) floor($totalCents * ($weights[$i] / $totalWeight));
        }

        // Floor leaves a few cents unassigned; hand them to the biggest share.
        $remainder = $totalCents - array_sum($allocatedCents);
        if ($remainder !== 0) {
            $largest = array_keys($weights, max($weights))[0] ?? 0;
            $allocatedCents[$largest] += $remainder;
        }

        $result = [];
        foreach ($targets as $i => $target) {
            $result[$i] = [
                'head_count' => max(1, (int) ($target['head_count'] ?? 1)),
                'basis_value' => $target['basis_value'] ?? null,
                'allocated_cost' => round($allocatedCents[$i] / 100, 2),
            ];
        }

        return $result;
    }
}
