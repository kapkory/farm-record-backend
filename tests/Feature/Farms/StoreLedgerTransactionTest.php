<?php

use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('stores a planting expense using double entry posting', function () {
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Demo Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);

    FarmerUser::create([
        'farmer_id' => $farmer->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 1,
    ]);

    $farm = Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'North Farm',
        'location' => 'Nakuru',
        'type' => 'crop',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $crop = Crop::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Maize',
        'description' => 'Main test crop',
    ]);

    $expenseAccount = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Seeds & Seedlings',
        'slug' => 'seeds-seedlings',
        'type' => 'expense',
        'farmer_id' => $farmer->id,
        'is_system' => false,
        'status' => 1,
    ]);

    $mobileMoney = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Mobile Money',
        'slug' => 'mobile-money',
        'type' => 'asset',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);

    $planting = Planting::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farm/transactions', [
        'date' => '2026-03-17',
        'payment_method' => 'mobile_money',
        'description' => 'sample',
        'reference_number' => 'sdds',
        'transaction_for' => 'planting',
        'type' => 'expense',
        'transaction_uuid' => $planting->uuid,
        'entries' => [
            [
                'ledger_account_id' => $expenseAccount->id,
                'amount' => 3000,
                'quantity' => null,
                'unit_cost' => null,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.farm_id', $farm->id)
        ->assertJsonPath('data.transactionable_type', App\Models\Core\Planting::class)
        ->assertJsonCount(2, 'data.entries');

    $this->assertDatabaseCount('ledger_transactions', 1);
    $this->assertDatabaseCount('ledger_entries', 2);

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $expenseAccount->id,
        'type' => 'debit',
        'amount' => '3000.00',
    ]);

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $mobileMoney->id,
        'type' => 'credit',
        'amount' => '3000.00',
    ]);
});

it('rejects an account that does not match the requested transaction type', function () {
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Demo Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);

    FarmerUser::create([
        'farmer_id' => $farmer->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 1,
    ]);

    $farm = Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'North Farm',
        'location' => 'Nakuru',
        'type' => 'crop',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $crop = Crop::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Maize',
        'description' => 'Main test crop',
    ]);

    $assetAccount = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Farm Cash',
        'slug' => 'farm-cash',
        'type' => 'asset',
        'farmer_id' => $farmer->id,
        'is_system' => false,
        'status' => 1,
    ]);

    LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Mobile Money',
        'slug' => 'mobile-money',
        'type' => 'asset',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);

    $planting = Planting::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farm/transactions', [
        'date' => '2026-03-17',
        'payment_method' => 'mobile_money',
        'description' => 'sample',
        'reference_number' => 'sdds',
        'transaction_for' => 'planting',
        'type' => 'expense',
        'transaction_uuid' => $planting->uuid,
        'entries' => [
            [
                'ledger_account_id' => $assetAccount->id,
                'amount' => 3000,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['entries.0.ledger_account_id']);
});

