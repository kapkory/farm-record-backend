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

const REPORTS_BASE_URI = '/api/v1/farms/farm/reports';

it('returns a profit and loss statement grouped by plantings', function () {
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Green Growers',
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
        'name' => 'Demo Farm',
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

    $revenueAccount = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Crop Sales',
        'slug' => 'crop-sales',
        'type' => 'revenue',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);

    $expenseAccount = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Seeds & Seedlings',
        'slug' => 'seeds-seedlings',
        'type' => 'expense',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);

    $plantingA = Planting::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'description' => 'First planting',
        'user_id' => $user->id,
    ]);

    $plantingB = Planting::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-02-10',
        'purpose' => 'commercial',
        'description' => 'Second planting',
        'user_id' => $user->id,
    ]);

    $expenseTxn = LedgerTransaction::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'date' => '2026-03-05',
        'payment_method' => 'cash',
        'description' => 'Bought seedlings',
        'reference_number' => 'EXP-001',
        'transactionable_type' => Planting::class,
        'transactionable_id' => $plantingA->id,
        'farmer_id' => $farmer->id,
    ]);

    LedgerEntry::create([
        'uuid' => (string) Str::orderedUuid(),
        'ledger_transaction_id' => $expenseTxn->id,
        'ledger_account_id' => $expenseAccount->id,
        'type' => 'debit',
        'amount' => 4500,
        'quantity' => 150,
        'unit_price' => 30,
        'user_id' => $user->id,
    ]);

    $revenueTxn = LedgerTransaction::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'date' => '2026-03-20',
        'payment_method' => 'bank',
        'description' => 'Sold tomatoes',
        'reference_number' => 'REV-001',
        'transactionable_type' => Planting::class,
        'transactionable_id' => $plantingA->id,
        'farmer_id' => $farmer->id,
    ]);

    LedgerEntry::create([
        'uuid' => (string) Str::orderedUuid(),
        'ledger_transaction_id' => $revenueTxn->id,
        'ledger_account_id' => $revenueAccount->id,
        'type' => 'credit',
        'amount' => 10000,
        'quantity' => 100,
        'unit_price' => 100,
        'user_id' => $user->id,
    ]);

    $plantingBExpenseTxn = LedgerTransaction::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'date' => '2026-02-15',
        'payment_method' => 'cash',
        'description' => 'Bought seedlings for planting B',
        'reference_number' => 'EXP-002',
        'transactionable_type' => Planting::class,
        'transactionable_id' => $plantingB->id,
        'farmer_id' => $farmer->id,
    ]);

    LedgerEntry::create([
        'uuid' => (string) Str::orderedUuid(),
        'ledger_transaction_id' => $plantingBExpenseTxn->id,
        'ledger_account_id' => $expenseAccount->id,
        'type' => 'debit',
        'amount' => 2000,
        'quantity' => 50,
        'unit_price' => 40,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(REPORTS_BASE_URI.'/profit-and-loss/plantings');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.planting.id', $plantingA->id)
        ->assertJsonPath('data.0.planting.crop.name', 'Tomato')
        ->assertJsonPath('data.0.totals.revenue', 10000)
        ->assertJsonPath('data.0.totals.expenses', 4500)
        ->assertJsonPath('data.0.totals.net_profit', 5500)
        ->assertJsonPath('data.1.planting.id', $plantingB->id)
        ->assertJsonPath('data.1.totals.revenue', 0)
        ->assertJsonPath('data.1.totals.expenses', 2000)
        ->assertJsonPath('data.1.totals.net_profit', -2000);
});

