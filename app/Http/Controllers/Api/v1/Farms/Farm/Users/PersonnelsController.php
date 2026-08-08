<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Users;

use App\Http\Controllers\Controller;
use App\Models\Core\Farm;
use App\Models\Core\FarmPersonnel;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PersonnelsController extends Controller
{
    use ApiResponse;

    public function storePersonnel(Request $request): JsonResponse
    {
        $wantsLogin = $request->boolean('create_login');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:farm_personnels,phone',
            'role' => 'required|string|max:100',
            'notes' => 'nullable|string',
            // Optional login: give this person their own sign-in.
            'create_login' => 'nullable|boolean',
            // Email is optional for a plain record, but required and must be a
            // real, unused sign-in address when a login is wanted.
            'email' => array_filter([
                $wantsLogin ? 'required' : 'nullable',
                'email', 'max:255', 'unique:farm_personnels,email',
                $wantsLogin ? 'unique:users,email' : null,
            ]),
            'password' => $wantsLogin ? ['required', Password::defaults()] : ['nullable'],
            'access_level' => 'nullable|in:staff,manager',
            // Optional: pin this login to specific farms. Omitted means every
            // farm of the farmer.
            'farm_uuids' => 'nullable|array',
            'farm_uuids.*' => 'uuid',
        ]);

        try {
            $farmerIds = $request->user()->farmers()->pluck('farmers.id');

            if ($farmerIds->isEmpty()) {
                return $this->errorResponse('No farmer profile found for this user.', 403);
            }

            $farmerId = $farmerIds->first();

            $personnel = DB::transaction(function () use ($request, $data, $farmerId) {
                $loginUserId = null;

                if ($request->boolean('create_login')) {
                    $loginUser = User::create([
                        'uuid' => (string) Str::orderedUuid(),
                        'name' => $data['name'],
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'],
                        'status' => 1,
                        'password' => Hash::make($data['password']),
                    ]);

                    // Attach to the same farmer so the login can see this farm's
                    // data, at the chosen access level (defaults to staff).
                    $loginUser->farmers()->attach($farmerId, [
                        'role' => $data['access_level'] ?? 'staff',
                        'status' => 1,
                    ]);

                    // Pin them to the chosen farms. No rows means unrestricted,
                    // so only write when specific farms were named.
                    $farmUuids = $data['farm_uuids'] ?? [];
                    if ($farmUuids !== []) {
                        $farmIds = Farm::query()
                            ->where('farmer_id', $farmerId)
                            ->whereIn('uuid', $farmUuids)
                            ->pluck('id');

                        $loginUser->assignedFarms()->sync($farmIds);
                    }

                    $loginUserId = $loginUser->id;
                }

                return FarmPersonnel::create([
                    'uuid' => (string) Str::orderedUuid(),
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'role' => $data['role'],
                    'notes' => $data['notes'] ?? null,
                    'farmer_id' => $farmerId,
                    'user_id' => $request->user()->id,
                    'login_user_id' => $loginUserId,
                    'status' => true,
                ]);
            });

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
            ->select('id', 'uuid', 'name', 'role', 'phone', 'email', 'notes', 'status', 'login_user_id', 'created_at')
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
