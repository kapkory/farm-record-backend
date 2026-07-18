<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Bees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bees\UpsertApiaryProfileRequest;
use App\Models\Core\AnimalGroup;
use App\Models\Core\ApiaryProfile;
use App\Models\Core\Farm;
use App\Services\Bees\HiveNamingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiariesController extends Controller
{
    use ApiResponse;

    public function __construct(protected HiveNamingService $namingService) {}

    public function getProfile(Request $request, string $uuid): JsonResponse
    {
        $apiary = $this->findOwnedApiary($request->user()->id, $uuid);

        if (! $apiary) {
            return $this->errorResponse('Apiary not found', 404);
        }

        $profile = ApiaryProfile::firstOrCreate(['animal_group_id' => $apiary->id]);

        return $this->successResponse($this->profilePayload($apiary, $profile), 'Apiary profile retrieved successfully');
    }

    public function upsertProfile(UpsertApiaryProfileRequest $request, string $uuid): JsonResponse
    {
        $apiary = $this->findOwnedApiary($request->user()->id, $uuid);

        if (! $apiary) {
            return $this->errorResponse('Apiary not found', 404);
        }

        try {
            $profile = ApiaryProfile::firstOrCreate(['animal_group_id' => $apiary->id]);

            $profile->fill($request->safe()->only([
                'naming_prefix', 'naming_scheme', 'default_harvest_interval_days',
            ]));

            // A start letter re-seeds the counter, but only before any hive
            // exists — existing codes are painted on physical boxes.
            $startLetter = $request->validated('start_letter');
            if ($startLetter && $apiary->hives()->count() === 0) {
                $profile->next_sequence = ord(strtoupper($startLetter)) - ord('A') + 1;
            }

            $profile->save();

            return $this->successResponse($this->profilePayload($apiary, $profile), 'Apiary profile saved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save apiary profile', 500, ['exception' => $e->getMessage()]);
        }
    }

    protected function profilePayload(AnimalGroup $apiary, ApiaryProfile $profile): array
    {
        return [
            'apiary_uuid' => $apiary->uuid,
            'apiary_name' => $apiary->name,
            'naming_prefix' => $profile->naming_prefix,
            'naming_scheme' => $profile->naming_scheme,
            'next_sequence' => $profile->next_sequence,
            'next_code_preview' => $this->namingService->codeFor(
                $profile->next_sequence,
                $profile->naming_prefix,
                $profile->naming_scheme
            ),
            'default_harvest_interval_days' => $profile->default_harvest_interval_days,
            'hive_count' => $apiary->hives()->count(),
        ];
    }

    protected function findOwnedApiary(int $userId, string $uuid): ?AnimalGroup
    {
        return AnimalGroup::query()
            ->where('uuid', $uuid)
            ->whereIn('farm_id', Farm::farmerOwned($userId)->pluck('id'))
            ->first();
    }
}
