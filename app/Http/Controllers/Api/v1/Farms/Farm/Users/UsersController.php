<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class UsersController extends Controller
{
    use ApiResponse;

    //TODO when creating farm users, created_at is not being set in the pivot table, need to investigate and fix that, otherwise the sorting by created_at will not work as expected
    public function listUsers(): JsonResponse
    {
        $farmerIds = request()->user()->farmers()->pluck('farmers.id');

        $users = User::query()
            ->join('farmer_users', 'farmer_users.user_id', '=', 'users.id')
            ->whereIn('farmer_users.farmer_id', $farmerIds)
            ->select('users.name','farmer_users.role', 'users.email', 'users.phone', 'farmer_users.created_at')
            ->distinct()
            ->orderByDesc('farmer_users.created_at')
            ->get()
            ->map(fn ($user) => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'date_created' => $user->created_at?->diffForHumans(),
            ])
            ->values();

        return $this->successResponse($users, 'Farm users retrieved successfully');
    }
}
