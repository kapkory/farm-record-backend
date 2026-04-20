<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Animals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreBreedingRequest;
use App\Http\Requests\Farms\UpdateBreedingRequest;
use App\Http\Resources\Farms\Farm\AnimalBreedingResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\Farm;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BreedingsController extends Controller
{
    use ApiResponse;

    public function store(StoreBreedingRequest $request): JsonResponse
    {
        try {
            $dam = Animal::where('uuid', $request->validated('dam_id'))
                ->with(['farm', 'animalType', 'animalBreed'])
                ->firstOrFail();

            $sire = $request->filled('sire_id')
                ? Animal::where('uuid', $request->validated('sire_id'))
                    ->with(['animalType', 'animalBreed'])
                    ->first()
                : null;

            $breeding = AnimalBreeding::create([
                'farm_id'             => $dam->farm_id,
                'dam_id'              => $dam->id,
                'sire_id'             => $sire?->id,
                'sire_type'           => $request->validated('sire_type'),
                'service_date'        => $request->validated('service_date'),
                'expected_birth_date' => $request->validated('expected_birth_date'),
                'status'              => $request->validated('status', 'pending'),
                'ai_straw_code'       => $request->validated('ai_straw_code'),
                'ai_bull_name'        => $request->validated('ai_bull_name'),
                'ai_technician'       => $request->validated('ai_technician'),
                'notes'               => $request->validated('notes'),
                'user_id'             => $request->user()->id,
            ]);

            // Set already-loaded relations to avoid extra queries
            $breeding->setRelation('dam', $dam);
            if ($sire) {
                $breeding->setRelation('sire', $sire);
            }

            return $this->successResponse(
                new AnimalBreedingResource($breeding),
                'Breeding record created successfully',
                201
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save breeding record', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function update(UpdateBreedingRequest $request, string $uuid): JsonResponse
    {
        try {
            $breeding = AnimalBreeding::where('uuid', $uuid)
                ->with(['dam.animalType', 'dam.animalBreed', 'sire.animalType', 'sire.animalBreed'])
                ->firstOrFail();

            if (! Farm::farmerOwned(auth()->id())->where('id', $breeding->farm_id)->exists()) {
                return $this->errorResponse('Breeding record not found', 404);
            }

            $payload = [];

            // Resolve dam if provided
            if ($request->has('dam_id')) {
                $dam = Animal::where('uuid', $request->validated('dam_id'))
                    ->with(['farm', 'animalType', 'animalBreed'])
                    ->firstOrFail();
                $payload['dam_id'] = $dam->id;
                $breeding->setRelation('dam', $dam);
            }

            // Resolve sire if provided
            if ($request->has('sire_id')) {
                $sire = $request->filled('sire_id')
                    ? Animal::where('uuid', $request->validated('sire_id'))
                        ->with(['animalType', 'animalBreed'])
                        ->first()
                    : null;
                $payload['sire_id'] = $sire?->id;
                $breeding->setRelation('sire', $sire);
            }

            // Map all other optional scalar fields
            foreach (['sire_type', 'service_date', 'expected_birth_date', 'status', 'ai_straw_code', 'ai_bull_name', 'ai_technician', 'notes'] as $field) {
                if ($request->has($field)) {
                    $payload[$field] = $request->validated($field);
                }
            }

            $breeding->update($payload);

            return $this->successResponse(
                new AnimalBreedingResource($breeding),
                'Breeding record updated successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update breeding record', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listBreedings(string $uuid): JsonResponse
    {
        try {
            $animal = Animal::where('uuid', $uuid)->firstOrFail();

            if (! Farm::farmerOwned(auth()->id())->where('id', $animal->farm_id)->exists()) {
                return $this->errorResponse('Animal not found', 404);
            }

            $breedings = AnimalBreeding::with([
                'dam.animalType',
                'dam.animalBreed',
                'sire.animalType',
                'sire.animalBreed',
            ])
                ->where(function ($query) use ($animal) {
                    $query->where('dam_id', $animal->id)
                          ->orWhere('sire_id', $animal->id);
                })
                ->latest('service_date')
                ->get();

            return $this->successResponse(
                AnimalBreedingResource::collection($breedings),
                'Breedings retrieved successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve breedings', 500, ['exception' => $e->getMessage()]);
        }
    }
}
