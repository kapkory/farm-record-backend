<?php

use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\LedgerAccount;
use App\Models\Core\LedgerEntry;
use App\Models\Core\LedgerTransaction;
use App\Models\Core\Planting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const TRANSACTIONS_BASE_URI = '/api/v1/farms/farm/transactions';

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

    $response = $this->actingAs($user, 'sanctum')->postJson(TRANSACTIONS_BASE_URI, [
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

it('lists transactions in the frontend resource shape', function () {
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
        'name' => 'Tomato',
        'description' => 'Tomato crop',
    ]);

    $planting = Planting::create([
        'uuid' => 'planting-uuid',
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);

    $account = LedgerAccount::create([
        'uuid' => 'ledger-account-uuid',
        'name' => 'Seedlings Purchase',
        'slug' => 'seedlings-purchase',
        'type' => 'expense',
        'farmer_id' => $farmer->id,
        'is_system' => false,
        'status' => 1,
    ]);

    $contra = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Mobile Money',
        'slug' => 'mobile-money',
        'type' => 'asset',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);

    $transaction = LedgerTransaction::create([
        'uuid' => 'txn-uuid',
        'farm_id' => $farm->id,
        'date' => '2026-03-15',
        'payment_method' => 'mobile_money',
        'description' => 'Bought tomato seedlings',
        'reference_number' => 'MPESA-QL91XZ2',
        'transactionable_type' => Planting::class,
        'transactionable_id' => $planting->id,
        'farmer_id' => $farmer->id,
    ]);

    LedgerEntry::create([
        'uuid' => (string) Str::orderedUuid(),
        'ledger_transaction_id' => $transaction->id,
        'ledger_account_id' => $account->id,
        'type' => 'debit',
        'amount' => 4500,
        'quantity' => 150,
        'unit_price' => 30,
        'user_id' => $user->id,
    ]);

    LedgerEntry::create([
        'uuid' => (string) Str::orderedUuid(),
        'ledger_transaction_id' => $transaction->id,
        'ledger_account_id' => $contra->id,
        'type' => 'credit',
        'amount' => 4500,
        'quantity' => null,
        'unit_price' => null,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson(TRANSACTIONS_BASE_URI.'/list');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', 'txn-uuid')
        ->assertJsonPath('data.0.date', '2026-03-15')
        ->assertJsonPath('data.0.payment_method', 'mobile_money')
        ->assertJsonPath('data.0.description', 'Bought tomato seedlings')
        ->assertJsonPath('data.0.reference_number', 'MPESA-QL91XZ2')
        ->assertJsonPath('data.0.transaction_for', 'planting')
        ->assertJsonPath('data.0.transaction_uuid', 'planting-uuid')
        ->assertJsonCount(1, 'data.0.ledger_entries')
        ->assertJsonPath('data.0.ledger_entries.0.ledger_account_id', $account->id)
        ->assertJsonPath('data.0.ledger_entries.0.amount', 4500)
        ->assertJsonPath('data.0.ledger_entries.0.quantity', 150)
        ->assertJsonPath('data.0.ledger_entries.0.unit_cost', 30)
        ->assertJsonPath('data.0.ledger_entries.0.ledger_account.uuid', 'ledger-account-uuid')
        ->assertJsonPath('data.0.ledger_entries.0.ledger_account.name', 'Seedlings Purchase')
        ->assertJsonPath('data.0.ledger_entries.0.ledger_account.type', 'expense');
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

    $response = $this->actingAs($user, 'sanctum')->postJson(TRANSACTIONS_BASE_URI, [
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

