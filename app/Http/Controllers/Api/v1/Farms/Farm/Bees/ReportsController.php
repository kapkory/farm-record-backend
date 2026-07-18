<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Bees;

use App\Http\Controllers\Controller;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\Production;
use App\Services\Bees\BeeProduct;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    use ApiResponse;

    public function production(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_by' => ['nullable', Rule::in(['farm', 'apiary', 'hive'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'product' => ['nullable', Rule::in(BeeProduct::keys())],
        ]);

        $groupBy = $validated['group_by'] ?? 'farm';
        $farmIds = Farm::farmerOwned($request->user()->id)->pluck('id');

        [$groupTable, $groupJoin, $labelColumn] = match ($groupBy) {
            'farm' => ['farms', 'hives.farm_id', 'farms.name'],
            'apiary' => ['animal_groups', 'hives.animal_group_id', 'animal_groups.name'],
            'hive' => [null, null, null],
        };

        $query = Production::query()
            ->join('hives', 'hives.id', '=', 'productions.productionable_id')
            ->where('productions.productionable_type', (new Hive)->getMorphClass())
            ->whereIn('hives.farm_id', $farmIds)
            ->whereNull('hives.deleted_at');

        if (! empty($validated['from'])) {
            $query->whereDate('productions.date', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('productions.date', '<=', $validated['to']);
        }
        if (! empty($validated['product'])) {
            $query->where('productions.name', $validated['product']);
        }

        if ($groupBy === 'hive') {
            $query->selectRaw(
                'hives.uuid as group_uuid, hives.code as group_label, '.
                'productions.name as product, productions.unit as unit, '.
                'SUM(productions.quantity) as total_quantity'
            )->groupBy('hives.uuid', 'hives.code', 'productions.name', 'productions.unit');
        } else {
            $query->join($groupTable, "{$groupTable}.id", '=', $groupJoin)
                ->selectRaw(
                    "{$groupTable}.uuid as group_uuid, {$labelColumn} as group_label, ".
                    'productions.name as product, productions.unit as unit, '.
                    'SUM(productions.quantity) as total_quantity'
                )->groupBy("{$groupTable}.uuid", $labelColumn, 'productions.name', 'productions.unit');
        }

        $rows = $query->orderBy('group_label')->orderBy('product')->get()
            ->map(fn ($row) => [
                'group_uuid' => $row->group_uuid,
                'group_label' => $row->group_label,
                'product' => $row->product,
                'unit' => $row->unit,
                'total_quantity' => round((float) $row->total_quantity, 2),
            ]);

        return $this->successResponse([
            'group_by' => $groupBy,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'rows' => $rows,
        ], 'Bee production report retrieved successfully');
    }
}
