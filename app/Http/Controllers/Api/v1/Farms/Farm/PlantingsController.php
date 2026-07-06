<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Models\Core\Farm;
use App\Models\Core\Field;
use App\Models\Core\Planting;
use App\Models\Core\Schedule;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PlantingsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    /**
     * return planting's index view
     */
    public function index()
    {
        return view($this->folder.'index', [

        ]);
    }

    /**
     * store planting
     */
    public function storePlanting(Request $request)
    {
        $data = $request->validate([
            'uuid' => 'nullable|uuid',
            'farm_uuid' => 'required|uuid|exists:farms,uuid',
            'crop_id' => 'required|integer|exists:crops,id',
            'date_planted' => 'required|date',
            'purpose' => 'string|required',
        ]);

        unset($data['farm_uuid']);
        unset($data['field_uuid']);

        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Planting::class,
            fn (Planting $planting) => Farm::farmerOwned($request->user()->id)->where('id', $planting->farm_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse($existing, 'Planting already saved');
        }

        try {
            $farmId = Farm::where('uuid', $request->input('farm_uuid'))->first()->id;
            if (\request('field_uuid') != '') {
                $data['field_id'] = Field::where('uuid', $request->input('field_uuid'))->first()->id;
            }

            if (\request('variety_id') != '') {
                $data['crop_variety_id'] = $request->input('variety_id');
            }

            if (\request('planting_schedule_uuid') != '') {
                $data['schedule_id'] = Schedule::where('uuid', $request->input('planting_schedule_uuid'))->first()->id;
            }

            $data['farm_id'] = $farmId;
            $data['description'] = \request('description');
            $data['quantity_planted'] = \request('quantity_planted');
            if (! isset($data['user_id'])) {
                if (Schema::hasColumn('plantings', 'user_id')) {
                    $data['user_id'] = request()->user()->id;
                }
            }
            $data['uuid'] = $uuid;
            $planting = Planting::create($data);

            return $this->successResponse($planting, 'Planting created successfully', 201);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Planting::class, $uuid)) {
                return $this->successResponse($replayed, 'Planting already saved');
            }

            return $this->errorResponse('Failed to save planting', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
x     * return planting values scoped to the authenticated user's farms
     */
    public function listPlantings($farm_uuid = null)
    {
        $userId = auth()->id();

        // Get farm IDs the authenticated user has access to
        $farmIds = Farm::farmerOwned($userId)->pluck('id');

        $query = Planting::leftJoin('crops', 'crops.id', '=', 'plantings.crop_id')
            ->leftJoin('crop_varieties', 'crop_varieties.id', '=', 'plantings.crop_variety_id')
            ->leftJoin('fields', 'fields.id', '=', 'plantings.field_id')
            ->select(
                'plantings.id', 'plantings.uuid', 'plantings.farm_id',
                'date_planted', 'purpose', 'crops.name as crop',
                'crop_varieties.name as variety', 'fields.name as field',
                'expected_harvest_date', 'actual_harvest_date',
                'quantity_planted', 'plantings.created_at'
            )
            ->whereIn('plantings.farm_id', $farmIds);

        if ($farm_uuid) {
            $farm = Farm::where('uuid', $farm_uuid)->whereIn('id', $farmIds)->first();
            if (! $farm) {
                return $this->errorResponse('Farm not found or access denied', 404);
            }
            $query->where('plantings.farm_id', $farm->id);
        }

        $plantings = $query->orderByDesc('plantings.created_at')->get();

        return $this->successResponse($plantings, 'Plantings retrieved successfully', 200);
    }

    /**
     * delete planting
     */
    public function destroyPlanting($planting_id)
    {
        $planting = Planting::findOrFail($planting_id);
        $planting->delete();

        return redirect()->back()->with('notice', ['type' => 'success', 'message' => 'Planting deleted successfully']);
    }
}
