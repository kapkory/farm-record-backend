<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Animals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreAnimalWeightRequest;
use App\Http\Resources\Farms\Farm\AnimalWeightResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalWeight;
use App\Models\Core\Farm;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Live weight readings for an animal or a sampled group.
 *
 * Reads and writes both go through the `weights()` morph relation, so
 * `weighable_type` is consistently the morph alias on both sides.
 */
class AnimalWeightsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function store(StoreAnimalWeightRequest $request): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            AnimalWeight::class,
            fn (AnimalWeight $weight) => $weight->user_id === $request->user()->id
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(new AnimalWeightResource($existing), 'Weight already saved');
        }

        try {
            $subject = $this->resolveSubject(
                $request->validated('weighable_type'),
                $request->validated('weighable_uuid')
            );

            $sampleSize = max(1, (int) $request->validated('sample_size'));
            $enteredKg = AnimalWeight::toKilograms((float) $request->validated('entered_value'), $request->validated('entered_unit'));

            // For a sample the farmer weighs several head together and enters
            // the total; the canonical figure stays per head so every reading
            // is comparable with every other.
            $isSample = $sampleSize > 1;

            $weight = $subject->weights()->create([
                'uuid' => $uuid,
                'measured_on' => $request->validated('measured_on'),
                'weight_kg' => $isSample ? round($enteredKg / $sampleSize, 3) : $enteredKg,
                'entered_value' => (float) $request->validated('entered_value'),
                'entered_unit' => $request->validated('entered_unit'),
                'sample_size' => $sampleSize,
                'sample_total_kg' => $isSample ? $enteredKg : null,
                'notes' => $request->validated('notes'),
                'user_id' => $request->user()->id,
            ]);

            return $this->successResponse(new AnimalWeightResource($weight), 'Weight saved successfully', 201);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, AnimalWeight::class, $uuid)) {
                return $this->successResponse(new AnimalWeightResource($replayed), 'Weight already saved');
            }

            return $this->errorResponse('Failed to save the weight', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listWeights(string $uuid): JsonResponse
    {
        $subject = AnimalGroup::where('uuid', $uuid)->first() ?: Animal::where('uuid', $uuid)->first();

        if (! $subject || ! Farm::farmerOwned(auth()->id())->where('id', $subject->farm_id)->exists()) {
            return $this->errorResponse('Animal record not found', 404);
        }

        // Newest first for the history table; the client re-sorts for the trend.
        $weights = $subject->weights()
            ->orderByDesc('measured_on')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(
            AnimalWeightResource::collection($weights),
            'Weights retrieved successfully'
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $weight = AnimalWeight::where('uuid', $uuid)->first();

        if (! $weight) {
            return $this->errorResponse('Weight record not found', 404);
        }

        $subject = $weight->weighable;

        if (! $subject || ! Farm::farmerOwned(auth()->id())->where('id', $subject->farm_id)->exists()) {
            return $this->errorResponse('Weight record not found', 404);
        }

        try {
            $weight->delete();

            return $this->successResponse(null, 'Weight deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete the weight', 500, ['exception' => $e->getMessage()]);
        }
    }

    protected function resolveSubject(string $type, string $uuid): Model
    {
        return match ($type) {
            'animal_group' => AnimalGroup::with('animalType')->where('uuid', $uuid)->firstOrFail(),
            default => Animal::with('animalType')->where('uuid', $uuid)->firstOrFail(),
        };
    }
}
