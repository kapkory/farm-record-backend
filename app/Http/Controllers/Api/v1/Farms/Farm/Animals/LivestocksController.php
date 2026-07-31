<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Animals;

use App\Http\Controllers\Controller;
use App\Http\Resources\Farms\Farm\LivestockResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LivestocksController extends Controller
{
    use ApiResponse;

    /**
     * Unified livestock listing — merges standalone animals and animal groups
     * into a single collection with a consistent response shape.
     *
     * Query params:
     *   tracking_type  — "individual" | "group"  (omit for both)
     *   animal_type_id — integer                 (filter by animal type)
     *   status         — string                  (filter by status)
     */
    public function index(Request $request, ?string $farm_uuid = null): JsonResponse
    {
        $farmIds = Farm::farmerOwned(auth()->id())->pluck('id');

        if ($farmIds->isEmpty()) {
            return $this->errorResponse('No farmer profile found for this user.', 403);
        }

        // Optional farm scope
        $scopedFarmId = null;
        if ($farm_uuid) {
            $farm = Farm::where('uuid', $farm_uuid)->whereIn('id', $farmIds)->first();
            if (! $farm) {
                return $this->errorResponse('Farm not found or access denied.', 404);
            }
            $scopedFarmId = $farm->id;
        }

        $trackingType = $request->query('tracking_type');
        $animalTypeId = $request->query('animal_type_id');
        $statusFilter = $request->query('status');

        $combined = collect();

        // ── Standalone individual animals ──────────────────────────────────────
        if ($trackingType !== 'group') {
            $animalQuery = Animal::with([
                'farm:id,uuid,name',
                'animalType:id,name',
                'animalBreed:id,name,purpose',
            ])
                ->withMax('treatments', 'date')
                ->standalone()
                ->whereIn('farm_id', $farmIds);

            if ($scopedFarmId) {
                $animalQuery->where('farm_id', $scopedFarmId);
            }
            if ($animalTypeId) {
                $animalQuery->where('animal_type_id', $animalTypeId);
            }
            if ($statusFilter) {
                $animalQuery->where('status', $statusFilter);
            }

            $combined = $combined->merge($animalQuery->orderByDesc('created_at')->get());
        }

        // ── Animal groups ──────────────────────────────────────────────────────
        if ($trackingType !== 'individual') {
            $groupQuery = AnimalGroup::with([
                'farm:id,uuid,name',
                'animalType:id,name',
                'animalBreed:id,name,purpose',
            ])
                ->withMax('treatments', 'date')
                ->whereIn('farm_id', $farmIds);

            if ($scopedFarmId) {
                $groupQuery->where('farm_id', $scopedFarmId);
            }
            if ($animalTypeId) {
                $groupQuery->where('animal_type_id', $animalTypeId);
            }
            // Group status is stored as tinyint: 0=inactive, 1=active, 2=sold_all, 3=archived
            if ($statusFilter !== null && $statusFilter !== '') {
                $statusMap = ['inactive' => 0, 'active' => 1, 'sold_all' => 2, 'archived' => 3];
                if (isset($statusMap[$statusFilter])) {
                    $groupQuery->where('status', $statusMap[$statusFilter]);
                }
            }

            $combined = $combined->merge($groupQuery->orderByDesc('created_at')->get());
        }

        // Sort merged collection by created_at descending
        $combined = $combined->sortByDesc('created_at')->values();

        return $this->successResponse(
            LivestockResource::collection($combined),
            'Livestock retrieved successfully'
        );
    }

    /**
     * Return a single livestock record (individual animal or group) by UUID.
     *
     * The UUID may belong to either an Animal or an AnimalGroup; both are
     * searched and only records that belong to the authenticated farmer's
     * farms are accessible.
     */
    public function show(string $animal_uuid): JsonResponse
    {
        $farmIds = Farm::farmerOwned(auth()->id())->pluck('id');

        if ($farmIds->isEmpty()) {
            return $this->errorResponse('No farmer profile found for this user.', 403);
        }

        // Try individual animal first
        $animal = Animal::with([
            'farm:id,uuid,name',
            'animalType:id,name',
            'animalBreed:id,name,purpose,gestation_days',
        ])
            ->withMax('treatments', 'date')
            ->standalone()
            ->whereIn('farm_id', $farmIds)
            ->where('uuid', $animal_uuid)
            ->first();

        if ($animal) {
            return $this->successResponse(
                new LivestockResource($animal),
                'Livestock retrieved successfully'
            );
        }

        // Fall back to animal group
        $group = AnimalGroup::with([
            'farm:id,uuid,name',
            'animalType:id,name',
            'animalBreed:id,name,purpose',
        ])
            ->withMax('treatments', 'date')
            ->whereIn('farm_id', $farmIds)
            ->where('uuid', $animal_uuid)
            ->first();

        if ($group) {
            return $this->successResponse(
                new LivestockResource($group),
                'Livestock retrieved successfully'
            );
        }

        return $this->errorResponse('Livestock not found.', 404);
    }
}
