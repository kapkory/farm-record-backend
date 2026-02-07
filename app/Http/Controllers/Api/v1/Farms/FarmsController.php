<?php

namespace App\Http\Controllers\Api\v1\Farms;

use App\Http\Controllers\Controller;
use App\Models\Core\Farm;
use App\Repositories\SearchRepo;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FarmsController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // simple pagination; adjust per requirements
        $perPage = (int) $request->query('per_page', 15);
        $farms = Farm::leftJoin('farmers','farmers.id','farms.farmer_id')
                ->farmerOwned(5)
                ->select('farms.*','farmers.display_name as owner');
//                ->get();
//        dd($farms);

         if (\request('all'))
            return $farms->get();
        return SearchRepo::of($farms)
            ->addColumn('status', function ($farm) {
                return $farm->status == 1 ? 'active' : 'inactive';
            })
            ->make();

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'numeric'],
            'size_unit' => ['nullable', 'string', 'max:50'],
            'established_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:mixed,crop,animal'],
            'ownership_type' => ['nullable', 'in:leased,owned,shared']
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $data = $validator->validated();
            $data['uuid'] = Str::orderedUuid();
            $data['established_date'] = $request->established_date != '' ? Carbon::parse($request->established_date)->toDateString() : null;
            $data['farmer_id'] = $request->user()->farmers()->wherePivot('role','owner')->first()->id; // assuming authenticated user is the farmer

            $farm = Farm::create($data);

            return $this->successResponse($farm, 'Farm created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create farm', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $farm = Farm::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $farm) {
            return $this->errorResponse('Farm not found', 404);
        }

        return $this->successResponse($farm, 'Farm retrieved successfully', 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $farm = Farm::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $farm) {
            return $this->errorResponse('Farm not found', 404);
        }

        $rules = [
            'farmer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'numeric'],
            'size_units' => ['nullable', 'string', 'max:50'],
            'established_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:mixed,crop,animal'],
            'ownership_type' => ['nullable', 'in:leased,owned,shared'],
            'status' => ['nullable', 'integer'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->toArray());
        }

        try {
            $farm->fill($validator->validated());
            $farm->save();

            return $this->successResponse($farm, 'Farm updated successfully', 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update farm', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $farm = Farm::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $farm) {
            return $this->errorResponse('Farm not found', 404);
        }

        try {
            $farm->delete();
            return $this->successResponse([], 'Farm deleted successfully', 200);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete farm', 500, ['exception' => $e->getMessage()]);
        }
    }
}
