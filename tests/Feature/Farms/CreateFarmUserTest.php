<?php

use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const FARM_USERS_CREATE_URI = '/api/v1/farms/farm/users';

function createActingFarmer(): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Test Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);

    FarmerUser::create([
        'farmer_id' => $farmer->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 1,
    ]);

    return [$user, $farmer];
}

it('creates a farm user and links them to the farmer', function () {
    [$owner, $farmer] = createActingFarmer();

    $response = $this->actingAs($owner, 'sanctum')->postJson(FARM_USERS_CREATE_URI, [
        'name' => 'Jane Wanjiku',
        'email' => 'jane@farmconsul.co.ke',
        'phone' => '0712345678',
        'role' => 'staff',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Jane Wanjiku')
        ->assertJsonPath('data.email', 'jane@farmconsul.co.ke')
        ->assertJsonPath('data.phone', '0712345678')
        ->assertJsonPath('data.role', 'staff');

    $newUser = User::where('email', 'jane@farmconsul.co.ke')->first();

    expect($newUser)->not->toBeNull();
    expect(Hash::check('FarmConsul@1', $newUser->password))->toBeTrue();
    expect($newUser->role)->toBe('member');
    expect($newUser->status)->toBe(1);

    $this->assertDatabaseHas('farmer_users', [
        'farmer_id' => $farmer->id,
        'user_id' => $newUser->id,
        'role' => 'staff',
        'status' => 1,
    ]);
});

it('rejects duplicate email on farm user creation', function () {
    [$owner] = createActingFarmer();

    User::factory()->create(['email' => 'duplicate@farm.co']);

    $response = $this->actingAs($owner, 'sanctum')->postJson(FARM_USERS_CREATE_URI, [
        'name' => 'Another Person',
        'email' => 'duplicate@farm.co',
        'role' => 'staff',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns 403 when the authenticated user has no farmer profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson(FARM_USERS_CREATE_URI, [
        'name' => 'Jane Wanjiku',
        'email' => 'jane2@farmconsul.co.ke',
        'role' => 'staff',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

