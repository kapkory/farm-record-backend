<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Animals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\RegisterBirthRequest;
use App\Http\Requests\Farms\StoreBreedingRequest;
use App\Http\Requests\Farms\UpdateBreedingRequest;
use App\Http\Resources\Farms\Farm\AnimalBreedingResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\AnimalEvent;
use App\Models\Core\Farm;
use App\Services\Animals\BirthRegistrar;
use App\Services\Animals\InbreedingChecker;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreedingsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function store(StoreBreedingRequest $request): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            AnimalBreeding::class,
            fn (AnimalBreeding $breeding) => Farm::farmerOwned($request->user()->id)->where('id', $breeding->farm_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new AnimalBreedingResource($existing->load(['dam.animalType', 'dam.animalBreed', 'sire.animalType', 'sire.animalBreed'])),
                'Breeding record already saved'
            );
        }

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
                'uuid' => $uuid,
                'farm_id' => $dam->farm_id,
                'dam_id' => $dam->id,
                'sire_id' => $sire?->id,
                'sire_type' => $request->validated('sire_type'),
                'service_date' => $request->validated('service_date'),
                'expected_birth_date' => $request->validated('expected_birth_date'),
                'status' => $request->validated('status', 'pending'),
                'ai_straw_code' => $request->validated('ai_straw_code'),
                'ai_bull_name' => $request->validated('ai_bull_name'),
                'ai_technician' => $request->validated('ai_technician'),
                'notes' => $request->validated('notes'),
                'user_id' => $request->user()->id,
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
            if ($replayed = $this->findAfterUniqueViolation($e, AnimalBreeding::class, $uuid)) {
                return $this->successResponse(
                    new AnimalBreedingResource($replayed->load(['dam.animalType', 'dam.animalBreed', 'sire.animalType', 'sire.animalBreed'])),
                    'Breeding record already saved'
                );
            }

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

    // POST /{uuid}/birth — record the outcome of a pregnancy: create an Animal
    // for each live offspring, log a birth event on the dam, and close the
    // breeding. Replays of the same request uuid return the stored result, so
    // the offline queue can retry safely.
    public function registerBirth(RegisterBirthRequest $request, string $uuid, BirthRegistrar $registrar): JsonResponse
    {
        $breeding = AnimalBreeding::where('uuid', $uuid)->first();

        if (! $breeding || ! Farm::farmerOwned($request->user()->id)->where('id', $breeding->farm_id)->exists()) {
            return $this->errorResponse('Breeding record not found', 404);
        }

        [$eventUuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            AnimalEvent::class,
            fn (AnimalEvent $event) => $event->user_id === $request->user()->id
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new AnimalBreedingResource($this->loadedBreeding($breeding)),
                'Birth already recorded'
            );
        }

        try {
            $registrar->register($breeding, $request->validated(), $request->user(), $eventUuid);

            return $this->successResponse(
                new AnimalBreedingResource($this->loadedBreeding($breeding->fresh())),
                'Birth recorded successfully',
                201
            );
        } catch (\Throwable $e) {
            if ($this->findAfterUniqueViolation($e, AnimalEvent::class, $eventUuid)) {
                return $this->successResponse(
                    new AnimalBreedingResource($this->loadedBreeding($breeding->fresh())),
                    'Birth already recorded'
                );
            }

            return $this->errorResponse('Failed to record the birth', 500, ['exception' => $e->getMessage()]);
        }
    }

    /** The relation set every breeding payload is rendered with. */
    protected function loadedBreeding(AnimalBreeding $breeding): AnimalBreeding
    {
        return $breeding->load([
            'dam.animalType', 'dam.animalBreed',
            'sire.animalType', 'sire.animalBreed',
            'offspring',
        ]);
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
                'offspring',
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

    // GET /calendar?window=30 — pending breedings with an expected birth date,
    // ordered by how soon they are due, for the dashboard and calendar.
    public function calendar(Request $request): JsonResponse
    {
        $farmIds = Farm::farmerOwned(auth()->id())->pluck('id');
        $window = (int) $request->query('window', 45);

        $breedings = AnimalBreeding::with(['dam.animalType', 'dam.animalBreed', 'sire', 'offspring'])
            ->whereIn('farm_id', $farmIds)
            ->where('status', 'pending')
            ->whereNotNull('expected_birth_date')
            ->where('expected_birth_date', '<=', now()->addDays($window))
            ->orderBy('expected_birth_date')
            ->get();

        return $this->successResponse(
            AnimalBreedingResource::collection($breedings),
            'Breeding calendar retrieved successfully'
        );
    }

    // GET /inbreeding-check?dam_uuid=&sire_uuid= — non-blocking relationship
    // risk check run before a mating is recorded.
    public function inbreedingCheck(Request $request, InbreedingChecker $checker): JsonResponse
    {
        $damUuid = $request->query('dam_uuid');
        $sireUuid = $request->query('sire_uuid');

        if (! $damUuid || ! $sireUuid) {
            return $this->successResponse(['related' => false, 'warnings' => []], 'No pair to check');
        }

        $farmIds = Farm::farmerOwned(auth()->id())->pluck('id');
        $dam = Animal::whereIn('farm_id', $farmIds)->where('uuid', $damUuid)->first();
        $sire = Animal::whereIn('farm_id', $farmIds)->where('uuid', $sireUuid)->first();

        if (! $dam || ! $sire) {
            return $this->successResponse(['related' => false, 'warnings' => []], 'Animal not found');
        }

        return $this->successResponse($checker->check($dam, $sire), 'Inbreeding check complete');
    }
}
