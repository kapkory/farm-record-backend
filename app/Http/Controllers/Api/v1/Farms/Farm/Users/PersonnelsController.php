<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Users;

use App\Http\Controllers\Controller;
use App\Models\Core\FarmPersonnel;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PersonnelsController extends Controller
{
    use ApiResponse;

    public function storePersonnel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:farm_personnels,phone',
            'email' => 'nullable|email|max:255|unique:farm_personnels,email',
            'role' => 'required|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $farmerIds = $request->user()->farmers()->pluck('farmers.id');

            if ($farmerIds->isEmpty()) {
                return $this->errorResponse('No farmer profile found for this user.', 403);
            }

            $personnel = FarmPersonnel::create([
                'uuid' => (string) Str::orderedUuid(),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'role' => $data['role'],
                'notes' => $data['notes'] ?? null,
                'farmer_id' => $farmerIds->first(),
                'user_id' => $request->user()->id,
                'status' => true,
            ]);

            return $this->successResponse($personnel, 'Personnel saved successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save personnel', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listPersonnels(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        $personnels = FarmPersonnel::query()
            ->whereIn('farmer_id', $farmerIds)
            ->select('id', 'uuid', 'name', 'role', 'phone', 'email', 'notes', 'status', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($personnel) {
                $personnel->created_at_human = Carbon::parse($personnel->created_at)->diffForHumans();
                $personnel->created_at = Carbon::parse($personnel->created_at)->toDateTimeString();
                return $personnel;
            })
            ->values();

        return $this->successResponse($personnels, 'Personnels retrieved successfully', 200);
    }
}
