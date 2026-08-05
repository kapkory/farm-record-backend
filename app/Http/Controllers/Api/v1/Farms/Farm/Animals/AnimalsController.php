<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Animals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreAnimalRequest;
use App\Http\Requests\Farms\UpdateAnimalRequest;
use App\Http\Resources\Farms\Farm\LivestockResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\TreatmentPlan;
use App\Services\Animals\AnimalPurchaseRecorder;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnimalsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function store(StoreAnimalRequest $request, AnimalPurchaseRecorder $purchaseRecorder): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Animal::class,
            fn (Animal $animal) => Farm::farmerOwned($request->user()->id)->where('id', $animal->farm_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new LivestockResource($existing->load($this->livestockRelations())),
                'Animal already saved'
            );
        }

        try {
            $group = $request->filled('animal_group_uuid')
                ? AnimalGroup::with('farm')->where('uuid', $request->validated('animal_group_uuid'))->firstOrFail()
                : null;
            $farm = $group?->farm ?? ($request->filled('farm_uuid')
                ? Farm::where('uuid', $request->validated('farm_uuid'))->firstOrFail()
                : null);

            $tagNumber = $request->validated('tag_number') ?? Animal::generateTagNumber();
            $name = $request->validated('name') ?? $tagNumber;

            $treatmentPlan = $request->filled('treatment_plan_uuid')
                ? TreatmentPlan::where('uuid', $request->validated('treatment_plan_uuid'))->first()
                : null;

            [$damId, $sireId] = $this->resolveParents($request, $farm->id);

            $animal = Animal::create([
                'uuid' => $uuid,
                'farm_id' => $farm->id,
                'farmer_id' => $farm->farmer_id,
                'animal_group_id' => $group?->id,
                'animal_type_id' => $group?->animal_type_id ?? $request->validated('animal_type_id'),
                'animal_breed_id' => $request->validated('animal_breed_id') ?? $group?->animal_breed_id,
                'dam_id' => $damId,
                'sire_id' => $sireId,
                'treatment_plan_id' => $treatmentPlan?->id,
                'tag_number' => $tagNumber,
                'name' => $name,
                'gender' => $request->validated('gender') ?? 'unknown',
                'date_of_birth' => $request->validated('date_of_birth') ?? null,
                'acquisition_date' => $request->validated('acquisition_date') ?? null,
                'acquisition_type' => $request->validated('acquisition_type') ?? 'born',
                'purchase_price' => $request->validated('purchase_price'),
                'status' => $request->validated('status') ?? 'active',
                'notes' => $request->validated('notes') ?? null,
                'user_id' => $request->user()->id,
            ]);

            // What the farmer paid belongs in the ledger, not just in a column
            // — this is what makes it show in the animal's Costs tab.
            $purchaseRecorder->record($request->user(), $animal->load('farm'));

            return $this->successResponse(
                new LivestockResource($animal->load($this->livestockRelations())),
                'Animal saved successfully',
                201
            );
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Animal::class, $uuid)) {
                return $this->successResponse(
                    new LivestockResource($replayed->load($this->livestockRelations())),
                    'Animal already saved'
                );
            }

            return $this->errorResponse('Failed to save animal', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listByGroup(string $group_uuid): JsonResponse
    {
        $group = AnimalGroup::where('uuid', $group_uuid)->first();
        if (! $group || ! Farm::farmerOwned(auth()->id())->where('id', $group->farm_id)->exists()) {
            return $this->errorResponse('Animal group not found', 404);
        }

        $animals = Animal::with(['farm', 'animalGroup', 'animalType', 'animalBreed'])
            ->where('animal_group_id', $group->id)
            ->orderBy('name')
            ->get();

        return $this->successResponse(LivestockResource::collection($animals), 'Animals retrieved successfully');
    }

    public function listStandalone(string $farm_uuid): JsonResponse
    {
        $farm = Farm::where('uuid', $farm_uuid)->first();
        if (! $farm || ! Farm::farmerOwned(auth()->id())->where('id', $farm->id)->exists()) {
            return $this->errorResponse('Farm not found', 404);
        }

        $animals = Animal::with(['farm', 'animalGroup', 'animalType', 'animalBreed'])
            ->standalone()
            ->forFarm($farm->id)
            ->orderBy('name')
            ->get();

        return $this->successResponse(LivestockResource::collection($animals), 'Standalone animals retrieved successfully');
    }

    /**
     * Resolve dam/sire uuids to ids, scoped to the same farm.
     *
     * @return array{0: int|null, 1: int|null}
     */
    protected function resolveParents(Request $request, int $farmId): array
    {
        $damId = $request->filled('dam_uuid')
            ? Animal::where('uuid', $request->input('dam_uuid'))->where('farm_id', $farmId)->value('id')
            : null;
        $sireId = $request->filled('sire_uuid')
            ? Animal::where('uuid', $request->input('sire_uuid'))->where('farm_id', $farmId)->value('id')
            : null;

        return [$damId, $sireId];
    }

    public function show(string $uuid): JsonResponse
    {
        $animal = Animal::with(['farm', 'animalGroup', 'animalType', 'animalBreed', 'events', 'latestWeight'])
            ->where('uuid', $uuid)
            ->first();

        if (! $animal || ! Farm::farmerOwned(auth()->id())->where('id', $animal->farm_id)->exists()) {
            return $this->errorResponse('Animal not found', 404);
        }

        return $this->successResponse(new LivestockResource($animal), 'Animal retrieved successfully');
    }

    public function update(UpdateAnimalRequest $request, string $uuid, AnimalPurchaseRecorder $purchaseRecorder): JsonResponse
    {
        $animal = Animal::with('animalGroup', 'farm')->where('uuid', $uuid)->first();

        if (! $animal || ! Farm::farmerOwned($request->user()->id)->where('id', $animal->farm_id)->exists()) {
            return $this->errorResponse('Animal not found', 404);
        }

        try {
            $validated = $request->validated();
            $payload = [];

            // Only what was actually sent gets written. The previous version
            // rebuilt the whole row from `?? null`, so saving just a name blanked
            // the animal's sex, date of birth and notes.
            foreach ([
                'tag_number', 'name', 'gender', 'date_of_birth', 'acquisition_date',
                'acquisition_type', 'purchase_price', 'gestation_adjustment_days',
                'weighing_interval_days', 'status', 'notes', 'animal_breed_id',
            ] as $field) {
                if (array_key_exists($field, $validated)) {
                    $payload[$field] = $validated[$field];
                }
            }

            // Moving the animal between groups/farms, when asked.
            if (array_key_exists('animal_group_uuid', $validated)) {
                $group = $validated['animal_group_uuid']
                    ? AnimalGroup::with('farm')->where('uuid', $validated['animal_group_uuid'])->firstOrFail()
                    : null;
                $payload['animal_group_id'] = $group?->id;
                if ($group) {
                    $payload['farm_id'] = $group->farm_id;
                    $payload['animal_type_id'] = $group->animal_type_id;
                }
            }

            if (! isset($payload['farm_id']) && ! empty($validated['farm_uuid'])) {
                $payload['farm_id'] = Farm::where('uuid', $validated['farm_uuid'])->value('id');
            }

            if (! isset($payload['animal_type_id']) && ! empty($validated['animal_type_id'])) {
                $payload['animal_type_id'] = $validated['animal_type_id'];
            }

            if (array_key_exists('treatment_plan_uuid', $validated)) {
                $payload['treatment_plan_id'] = $validated['treatment_plan_uuid']
                    ? TreatmentPlan::where('uuid', $validated['treatment_plan_uuid'])->value('id')
                    : null;
            }

            foreach (['dam_uuid' => 'dam_id', 'sire_uuid' => 'sire_id'] as $input => $column) {
                if (array_key_exists($input, $validated)) {
                    $payload[$column] = $validated[$input]
                        ? Animal::where('uuid', $validated[$input])->value('id')
                        : null;
                }
            }

            $hadPurchaseValue = (float) ($animal->purchase_price ?? 0) > 0;

            $animal->update($payload);

            // A price added on a later edit still belongs in the ledger; one
            // that was already posted must not be posted twice.
            if (! $hadPurchaseValue) {
                $purchaseRecorder->record($request->user(), $animal->fresh()->load('farm'));
            }

            return $this->successResponse(
                new LivestockResource($animal->fresh()->load($this->livestockRelations())),
                'Animal updated successfully'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update animal', 500, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * The relations LivestockResource renders — farm name, type, breed and the
     * latest weight. Kept in one place so every response carries the same shape
     * as the livestock list the frontend caches.
     *
     * @return array<int, string>
     */
    protected function livestockRelations(): array
    {
        return ['farm', 'animalGroup', 'animalType', 'animalBreed', 'latestWeight'];
    }

    public function destroy(string $uuid): JsonResponse
    {
        $animal = Animal::where('uuid', $uuid)->first();
        if (! $animal) {
            return $this->errorResponse('Animal not found', 404);
        }

        try {
            $animal->delete();

            return $this->successResponse(null, 'Animal deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete animal', 500, ['exception' => $e->getMessage()]);
        }
    }
}
