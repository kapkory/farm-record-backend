<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Users;

use App\Http\Controllers\Controller;
use App\Models\Core\FarmerUser;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Mail\FarmUserWelcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    use ApiResponse;

    public function createFarmUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'role' => 'required|string|in:owner,manager,staff',
        ]);

        try {
            $farmerIds = $request->user()->farmers()->pluck('farmers.id');

            if ($farmerIds->isEmpty()) {
                return $this->errorResponse('No farmer profile found for this user.', 403);
            }

            $user = DB::transaction(function () use ($data, $farmerIds, $request) {
                $user = User::create([
                    'uuid' => (string) Str::orderedUuid(),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make('FarmConsul@1'),
                    'role' => 'member',
                    'status' => 1,
                ]);

                FarmerUser::create([
                    'farmer_id' => $farmerIds->first(),
                    'user_id' => $user->id,
                    'role' => $data['role'],
                    'status' => 1,
                ]);

                return $user;
            });

            $token = Password::createToken($user);
            $resetUrl = config('app.frontend_url').'/auth/reset-password?token='.$token.'&email='.urlencode($user->email);
            Mail::to($user->email)->queue(new FarmUserWelcome($user, $data['role'], $resetUrl));

            return $this->successResponse([
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $data['role'],
            ], 'Farm user created successfully', 201);

        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create farm user', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listUsers(): JsonResponse
    {
        $farmerIds = request()->user()->farmers()->pluck('farmers.id');

        $users = User::query()
            ->join('farmer_users', 'farmer_users.user_id', '=', 'users.id')
            ->whereIn('farmer_users.farmer_id', $farmerIds)
            ->select('users.name', 'farmer_users.role', 'users.email', 'users.phone', 'farmer_users.created_at')
            ->distinct()
            ->orderByDesc('farmer_users.created_at')
            ->get()
            ->map(fn ($user) => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'date_created' => $user->created_at
                    ? Carbon::parse($user->created_at)->diffForHumans()
                    : null,
            ])
            ->values();

        return $this->successResponse($users, 'Farm users retrieved successfully');
    }
}
