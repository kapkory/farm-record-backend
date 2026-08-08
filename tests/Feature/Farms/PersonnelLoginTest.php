<?php

use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\FarmPersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const PERSONNELS_URI = '/api/v1/farms/farm/users/personnels';

function ownerWithFarmer(): array
{
    $user = User::factory()->create();
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Owner Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 1]);

    return [$user, $farmer];
}

it('creates a personnel with a working login attached to the farmer', function () {
    [$owner, $farmer] = ownerWithFarmer();

    $response = $this->actingAs($owner, 'sanctum')->postJson(PERSONNELS_URI, [
        'name' => 'Peter Herder',
        'role' => 'worker',
        'phone' => '0700111222',
        'email' => 'peter@example.com',
        'create_login' => true,
        'password' => 'Str0ngPass!23',
        'access_level' => 'staff',
    ]);

    $response->assertCreated();

    // A real user was created and linked.
    $loginUser = User::where('email', 'peter@example.com')->first();
    expect($loginUser)->not->toBeNull();
    expect(Hash::check('Str0ngPass!23', $loginUser->password))->toBeTrue();

    $personnel = FarmPersonnel::where('email', 'peter@example.com')->first();
    expect($personnel->login_user_id)->toBe($loginUser->id);

    // Attached to the same farmer as staff, so the login can see the farm.
    $this->assertDatabaseHas('farmer_users', [
        'user_id' => $loginUser->id,
        'farmer_id' => $farmer->id,
        'role' => 'staff',
    ]);

    // Credential validity is covered by the Hash::check above — the stored
    // hash is exactly what the auth guard checks a sign-in against.
});

it('pins a personnel login to the farms it was given', function () {
    [$owner, $farmer] = ownerWithFarmer();

    $farm = App\Models\Core\Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'Only This Farm',
        'location' => 'Kitale',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $this->actingAs($owner, 'sanctum')->postJson(PERSONNELS_URI, [
        'name' => 'Pinned Pete',
        'role' => 'worker',
        'email' => 'pinned@example.com',
        'create_login' => true,
        'password' => 'Str0ngPass!23',
        'access_level' => 'staff',
        'farm_uuids' => [$farm->uuid],
    ])->assertCreated();

    $loginUser = User::where('email', 'pinned@example.com')->first();
    expect($loginUser->allowedFarmIds())->toBe([$farm->id]);
});

it('leaves a login unrestricted when no farms are named', function () {
    [$owner] = ownerWithFarmer();

    $this->actingAs($owner, 'sanctum')->postJson(PERSONNELS_URI, [
        'name' => 'Open Olive',
        'role' => 'manager',
        'email' => 'open@example.com',
        'create_login' => true,
        'password' => 'Str0ngPass!23',
        'access_level' => 'manager',
    ])->assertCreated();

    expect(User::where('email', 'open@example.com')->first()->allowedFarmIds())->toBeNull();
});

it('creates a plain personnel with no login when not requested', function () {
    [$owner] = ownerWithFarmer();

    $this->actingAs($owner, 'sanctum')->postJson(PERSONNELS_URI, [
        'name' => 'Casual Jane',
        'role' => 'casual',
        'phone' => '0700333444',
    ])->assertCreated();

    $personnel = FarmPersonnel::where('name', 'Casual Jane')->first();
    expect($personnel->login_user_id)->toBeNull();
    expect(User::where('name', 'Casual Jane')->exists())->toBeFalse();
});

it('requires an email and password when a login is requested', function () {
    [$owner] = ownerWithFarmer();

    $this->actingAs($owner, 'sanctum')->postJson(PERSONNELS_URI, [
        'name' => 'No Creds',
        'role' => 'worker',
        'create_login' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
});

it('rejects a login email already used by another user', function () {
    [$owner] = ownerWithFarmer();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($owner, 'sanctum')->postJson(PERSONNELS_URI, [
        'name' => 'Dup Email',
        'role' => 'worker',
        'email' => 'taken@example.com',
        'create_login' => true,
        'password' => 'Str0ngPass!23',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});
