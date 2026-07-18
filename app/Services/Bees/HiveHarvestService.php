<?php

namespace App\Services\Bees;

use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class HiveHarvestService
{
    /**
     * Record a harvest session: one Production per hive per product, all
     * sharing the session uuid in trace_number. Honey/comb-honey products
     * also advance each hive's last_harvested_at / next_harvest_due.
     *
     * Replaying the same session uuid (offline queue retry) returns the
     * stored session instead of duplicating rows.
     *
     * @return array{session: array, replayed: bool}
     */
    public function record(User $user, array $payload): array
    {
        $sessionUuid = $payload['uuid'];

        if ($existing = $this->findSession($user, $sessionUuid)) {
            return ['session' => $existing, 'replayed' => true];
        }

        $date = Carbon::parse($payload['date'])->startOfDay();
        $products = $this->normalizeProducts($payload['products']);

        $hives = $this->resolveOwnedHives($user, array_unique(array_merge(
            ...array_map(fn ($p) => array_keys($p['quantities']), $products)
        )));

        $session = DB::transaction(function () use ($user, $payload, $sessionUuid, $date, $products, $hives) {
            foreach ($products as $product) {
                foreach ($product['quantities'] as $hiveUuid => $quantity) {
                    $hive = $hives[$hiveUuid];

                    $hive->productions()->create([
                        'uuid' => (string) Str::orderedUuid(),
                        'name' => $product['product'],
                        'date' => $date->toDateString(),
                        'trace_number' => $sessionUuid,
                        'quantity' => $quantity,
                        'unit' => $product['unit'],
                        'grade' => $payload['grade'] ?? null,
                        'notes' => $payload['notes'] ?? null,
                        'user_id' => $user->id,
                    ]);
                }

                if (BeeProduct::affectsReadiness($product['product'])) {
                    foreach (array_keys($product['quantities']) as $hiveUuid) {
                        $this->advanceHarvestClock($hives[$hiveUuid], $date);
                    }
                }
            }

            return $this->buildSummary($sessionUuid, $date, $payload, $products, $hives);
        });

        return ['session' => $session, 'replayed' => false];
    }

    /** Returns the stored session summary for a replayed uuid, or null. */
    public function findSession(User $user, string $sessionUuid): ?array
    {
        $rows = Production::query()
            ->where('trace_number', $sessionUuid)
            ->where('productionable_type', (new Hive)->getMorphClass())
            ->with(['productionable.apiary.apiaryProfile'])
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $farmIds = Farm::farmerOwned($user->id)->pluck('id');
        $hives = $rows->pluck('productionable')->filter()->unique('id');

        if ($hives->isEmpty() || $hives->contains(fn (Hive $hive) => ! $farmIds->contains($hive->farm_id))) {
            throw new ModelNotFoundException('Harvest session not found.');
        }

        return $this->summaryFromRows($sessionUuid, $rows);
    }

    /** @return array<string, array{product: string, unit: string, quantities: array<string, float>}> */
    protected function normalizeProducts(array $products): array
    {
        $normalized = [];

        foreach ($products as $product) {
            $key = $product['product'];
            $unit = $product['unit'] ?? BeeProduct::defaultUnit($key);

            if (! empty($product['hives'])) {
                $quantities = collect($product['hives'])
                    ->mapWithKeys(fn ($row) => [$row['hive_uuid'] => round((float) $row['quantity'], 2)])
                    ->all();
            } elseif (isset($product['total_quantity'], $product['hive_uuids'])) {
                $quantities = $this->splitEvenly((float) $product['total_quantity'], $product['hive_uuids']);
            } else {
                throw new InvalidArgumentException(
                    "Product '{$key}' needs per-hive quantities or total_quantity + hive_uuids."
                );
            }

            if (empty($quantities)) {
                throw new InvalidArgumentException("Product '{$key}' has no hives selected.");
            }

            $normalized[] = ['product' => $key, 'unit' => $unit, 'quantities' => $quantities];
        }

        return $normalized;
    }

    /** Split a bucket total across hives; the last hive absorbs rounding so the sum stays exact. */
    protected function splitEvenly(float $total, array $hiveUuids): array
    {
        $hiveUuids = array_values(array_unique($hiveUuids));
        $count = count($hiveUuids);

        if ($count === 0) {
            return [];
        }

        $share = floor(($total / $count) * 100) / 100;
        $quantities = [];

        foreach ($hiveUuids as $i => $uuid) {
            $quantities[$uuid] = $i === $count - 1
                ? round($total - $share * ($count - 1), 2)
                : $share;
        }

        return $quantities;
    }

    /** @return array<string, Hive> keyed by uuid; 404 when any hive is missing or foreign. */
    protected function resolveOwnedHives(User $user, array $uuids): array
    {
        $farmIds = Farm::farmerOwned($user->id)->pluck('id');

        /** @var Collection<int, Hive> $hives */
        $hives = Hive::query()
            ->whereIn('uuid', $uuids)
            ->whereIn('farm_id', $farmIds)
            ->with('apiary.apiaryProfile')
            ->get();

        if ($hives->count() !== count($uuids)) {
            throw new ModelNotFoundException('One or more hives were not found.');
        }

        return $hives->keyBy('uuid')->all();
    }

    protected function advanceHarvestClock(Hive $hive, Carbon $date): void
    {
        // Backdated entries must not rewind an already-later harvest date.
        if ($hive->last_harvested_at && $hive->last_harvested_at->gt($date)) {
            return;
        }

        $hive->last_harvested_at = $date->toDateString();
        $hive->next_harvest_due = $date->copy()->addDays($hive->effectiveHarvestIntervalDays())->toDateString();
        $hive->save();
    }

    protected function buildSummary(string $sessionUuid, Carbon $date, array $payload, array $products, array $hives): array
    {
        $perHive = [];
        $totals = [];

        foreach ($products as $product) {
            $totals[] = [
                'product' => $product['product'],
                'unit' => $product['unit'],
                'quantity' => round(array_sum($product['quantities']), 2),
            ];

            foreach ($product['quantities'] as $hiveUuid => $quantity) {
                $perHive[$hiveUuid]['products'][] = [
                    'product' => $product['product'],
                    'unit' => $product['unit'],
                    'quantity' => $quantity,
                ];
            }
        }

        $hiveRows = [];
        $warnings = [];

        foreach ($perHive as $hiveUuid => $row) {
            $hive = $hives[$hiveUuid]->refresh();

            if ($hive->occupancy !== Hive::OCCUPANCY_OCCUPIED) {
                $warnings[] = "Hive {$hive->code} is marked '{$hive->occupancy}'.";
            }

            $hiveRows[] = [
                'uuid' => $hive->uuid,
                'code' => $hive->code,
                'name' => $hive->name,
                'products' => $row['products'],
                'last_harvested_at' => $hive->last_harvested_at?->toDateString(),
                'next_harvest_due' => $hive->next_harvest_due?->toDateString(),
            ];
        }

        return [
            'trace_number' => $sessionUuid,
            'date' => $date->toDateString(),
            'grade' => $payload['grade'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'totals' => $totals,
            'hives' => $hiveRows,
            'warnings' => $warnings,
        ];
    }

    /** Rebuild a session summary from stored production rows (replay path). */
    protected function summaryFromRows(string $sessionUuid, Collection $rows): array
    {
        $totals = $rows->groupBy(fn (Production $row) => $row->name.'|'.$row->unit)
            ->map(fn (Collection $group) => [
                'product' => $group->first()->name,
                'unit' => $group->first()->unit,
                'quantity' => round($group->sum('quantity'), 2),
            ])->values()->all();

        $hiveRows = $rows->groupBy('productionable_id')->map(function (Collection $group) {
            /** @var Hive $hive */
            $hive = $group->first()->productionable;

            return [
                'uuid' => $hive->uuid,
                'code' => $hive->code,
                'name' => $hive->name,
                'products' => $group->map(fn (Production $row) => [
                    'product' => $row->name,
                    'unit' => $row->unit,
                    'quantity' => (float) $row->quantity,
                ])->values()->all(),
                'last_harvested_at' => $hive->last_harvested_at?->toDateString(),
                'next_harvest_due' => $hive->next_harvest_due?->toDateString(),
            ];
        })->values()->all();

        $first = $rows->first();

        return [
            'trace_number' => $sessionUuid,
            'date' => $first->date?->toDateString(),
            'grade' => $first->grade,
            'notes' => $first->notes,
            'totals' => $totals,
            'hives' => $hiveRows,
            'warnings' => [],
        ];
    }
}
