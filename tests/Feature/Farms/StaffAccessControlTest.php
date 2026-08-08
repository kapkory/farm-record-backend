<?php

use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A farmer with two farms, an owner, and a staff login pinned to farm A only.
 */
function accessChain(): array
{
    $owner = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Access Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 1]);

    $makeFarm = fn (string $name) => Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => $name,
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $farmA = $makeFarm('Farm A');
    $farmB = $makeFarm('Farm B');

    $staff = User::factory()->create();
    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 1]);
    $staff->assignedFarms()->sync([$farmA->id]);

    return compact('owner', 'staff', 'farmer', 'farmA', 'farmB');
}

it('blocks staff from every money endpoint', function () {
    ['staff' => $staff, 'farmA' => $farmA] = accessChain();

    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/farms/farm/sales/list')->assertForbidden();
    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/farms/farm/sales/summary')->assertForbidden();
    $this->actingAs($staff, 'sanctum')
        ->getJson("/api/v1/farms/farm/transactions/list/farm/{$farmA->uuid}")->assertForbidden();
    $this->actingAs($staff, 'sanctum')
        ->getJson("/api/v1/farms/farm/salaries/list/{$farmA->uuid}")->assertForbidden();
    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/v1/farms/farm/reports/profit-and-loss/plantings')->assertForbidden();
});

it('lets the owner reach the same money endpoints', function () {
    ['owner' => $owner, 'farmA' => $farmA] = accessChain();

    $this->actingAs($owner, 'sanctum')->getJson('/api/v1/farms/farm/sales/list')->assertOk();
    $this->actingAs($owner, 'sanctum')->getJson('/api/v1/farms/farm/sales/summary')->assertOk();
    $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/farms/farm/salaries/list/{$farmA->uuid}")->assertOk();
});

it('limits a pinned staff login to their assigned farm', function () {
    ['staff' => $staff, 'owner' => $owner, 'farmA' => $farmA, 'farmB' => $farmB] = accessChain();

    // ?all=1 returns a bare array rather than a {data:…} envelope.
    $staffFarms = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/farms?all=1')
        ->assertOk()->json();
    $staffUuids = collect($staffFarms)->pluck('uuid');

    expect($staffUuids)->toContain($farmA->uuid);
    expect($staffUuids)->not->toContain($farmB->uuid);

    // The owner, with no assignments, still sees both.
    $ownerFarms = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/farms?all=1')
        ->assertOk()->json();
    expect(collect($ownerFarms)->pluck('uuid'))->toContain($farmA->uuid, $farmB->uuid);
});

it('hides an unassigned farm from a pinned staff login', function () {
    ['staff' => $staff, 'farmB' => $farmB] = accessChain();

    // Livestock on a farm they are not assigned to must 404, not leak.
    $this->actingAs($staff, 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/livestocks/list/{$farmB->uuid}")
        ->assertNotFound();
});

it('blocks staff from the chart of accounts', function () {
    ['staff' => $staff, 'owner' => $owner] = accessChain();

    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/v1/settings/system/ledgeraccounts/list')->assertForbidden();
    // That controller answers 201 on list; assert reachability, not the code.
    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/settings/system/ledgeraccounts/list')->assertSuccessful();
});

it('hides input costs from staff while still showing stock', function () {
    ['staff' => $staff, 'owner' => $owner, 'farmA' => $farmA, 'farmer' => $farmer] = accessChain();

    App\Models\Core\FarmInput::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farmA->id,
        'farmer_id' => $farmer->id,
        'name' => 'Triatix dip',
        'category' => 'dip',
        'quantity' => 500,
        'unit' => 'ml',
        'quantity_remaining' => 500,
        'total_cost' => 3500,
        'unit_cost' => 7,
        'purchase_date' => '2026-01-10',
        'user_id' => $owner->id,
    ]);

    $staffRow = $this->actingAs($staff, 'sanctum')
        ->getJson("/api/v1/farms/farm/inputs/list/{$farmA->uuid}")
        ->assertOk()->json('data.0');

    // Stock is operational information they need; the money is not.
    expect($staffRow['quantity_remaining'])->toEqual(500.0);
    expect($staffRow['total_cost'])->toBeNull();
    expect($staffRow['unit_cost'])->toBeNull();

    $ownerRow = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/farms/farm/inputs/list/{$farmA->uuid}")
        ->assertOk()->json('data.0');
    expect($ownerRow['total_cost'])->toEqual(3500.0);
});

it('reports role and farm limits on /api/user', function () {
    ['staff' => $staff, 'owner' => $owner, 'farmA' => $farmA] = accessChain();

    $this->actingAs($staff, 'sanctum')->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('role', 'staff')
        ->assertJsonPath('can_view_finances', false)
        ->assertJsonPath('allowed_farm_uuids', [$farmA->uuid]);

    $this->actingAs($owner, 'sanctum')->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('role', 'owner')
        ->assertJsonPath('can_view_finances', true)
        ->assertJsonPath('allowed_farm_uuids', null);
});
