<?php

use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const FARM_USERS_BASE_URI = '/api/v1/farms/farm/users/list';

it('returns only users from the current user farm memberships', function () {
    Carbon::setTestNow('2026-03-20 10:00:00');

    $currentUser = User::factory()->create([
        'name' => 'Owner User',
        'phone' => '0700000000',
    ]);

    $sharedFarmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Shared Farm',
        'type' => 'individual',
        'status' => 1,
    ]);

    $otherFarmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Other Farm',
        'type' => 'individual',
        'status' => 1,
    ]);

    FarmerUser::create([
        'farmer_id' => $sharedFarmer->id,
        'user_id' => $currentUser->id,
        'role' => 'owner',
        'status' => 1,
        'created_at' => '2026-03-20 10:00:00',
        'updated_at' => '2026-03-20 10:00:00',
    ]);

    $farmMate = User::factory()->create([
        'name' => 'Farm Mate',
        'phone' => '0711111111',
    ]);

    FarmerUser::create([
        'farmer_id' => $sharedFarmer->id,
        'user_id' => $farmMate->id,
        'role' => 'staff',
        'status' => 1,
        'created_at' => '2026-03-19 09:30:00',
        'updated_at' => '2026-03-19 09:30:00',
    ]);

    $outsider = User::factory()->create([
        'name' => 'Outside User',
        'phone' => '0722222222',
    ]);

    FarmerUser::create([
        'farmer_id' => $otherFarmer->id,
        'user_id' => $outsider->id,
        'role' => 'staff',
        'status' => 1,
        'created_at' => '2026-03-18 08:15:00',
        'updated_at' => '2026-03-18 08:15:00',
    ]);

    $response = $this->actingAs($currentUser, 'sanctum')->getJson(FARM_USERS_BASE_URI);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Owner User')
        ->assertJsonPath('data.0.phone', '0700000000')
        ->assertJsonPath('data.0.date_created', '2026-03-20 10:00:00')
        ->assertJsonPath('data.1.name', 'Farm Mate')
        ->assertJsonPath('data.1.phone', '0711111111')
        ->assertJsonPath('data.1.date_created', '2026-03-19 09:30:00');

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toBe(['Owner User', 'Farm Mate']);

    Carbon::setTestNow();
});

