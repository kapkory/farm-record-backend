<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\FarmPersonnel;
use App\Models\Core\LedgerAccount;
use App\Models\Core\LedgerTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const TXN_URI = '/api/v1/farms/farm/transactions';
const SALARY_URI = '/api/v1/farms/farm/salaries';

function farmExpenseChain(): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Farm Boss',
        'type' => 'individual',
        'status' => 1,
    ]);

    FarmerUser::create(['farmer_id' => $farmer->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 1]);

    $farm = Farm::create([
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'Home Farm',
        'location' => 'Eldoret',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    foreach ([
        ['Labour', 'expense'],
        ['Veterinary & Medicine', 'expense'],
        ['Cash', 'asset'],
    ] as [$name, $type]) {
        LedgerAccount::create([
            'uuid' => (string) Str::orderedUuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => $type,
            'farmer_id' => $farmer->id,
            'is_system' => true,
            'status' => 1,
        ]);
    }

    return compact('user', 'farmer', 'farm');
}

it('records a whole-farm livestock expense against the farm', function () {
    ['user' => $user, 'farmer' => $farmer, 'farm' => $farm] = farmExpenseChain();
    $vet = LedgerAccount::where('name', 'Veterinary & Medicine')->first();

    $response = $this->actingAs($user, 'sanctum')->postJson(TXN_URI, [
        'date' => '2026-07-01',
        'payment_method' => 'cash',
        'description' => 'Whole-herd deworming day',
        'transaction_for' => 'farm',
        'scope' => 'livestock',
        'type' => 'expense',
        'transaction_uuid' => $farm->uuid,
        'entries' => [['ledger_account_id' => $vet->id, 'amount' => 8000]],
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('ledger_transactions', [
        'transactionable_type' => Farm::class,
        'transactionable_id' => $farm->id,
        'scope' => 'livestock',
    ]);
});

it('lists whole-farm expenses under the farm, tagged by scope, without touching individual animals', function () {
    ['user' => $user, 'farmer' => $farmer, 'farm' => $farm] = farmExpenseChain();
    $vet = LedgerAccount::where('name', 'Veterinary & Medicine')->first();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Dairy Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);
    $animal = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'name' => 'Bahati',
        'gender' => 'female',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')->postJson(TXN_URI, [
        'date' => '2026-07-01',
        'payment_method' => 'cash',
        'description' => 'Whole-herd deworming day',
        'transaction_for' => 'farm',
        'scope' => 'livestock',
        'type' => 'expense',
        'transaction_uuid' => $farm->uuid,
        'entries' => [['ledger_account_id' => $vet->id, 'amount' => 8000]],
    ])->assertCreated();

    // Shows in the farm's costs list, tagged livestock…
    $farmList = $this->actingAs($user, 'sanctum')->getJson(TXN_URI."/list/farm/{$farm->uuid}");
    $farmList->assertOk();
    $farmRows = collect($farmList->json('data'));
    expect($farmRows)->toHaveCount(1);
    expect($farmRows->first()['scope'])->toBe('livestock');

    // …but is NOT summed into any single animal's ledger (would multi-count).
    $animalList = $this->actingAs($user, 'sanctum')->getJson(TXN_URI."/list/animal/{$animal->uuid}");
    $animalList->assertOk();
    expect(collect($animalList->json('data')))->toHaveCount(0);
});

it('records a salary as a whole-farm Labour expense', function () {
    ['user' => $user, 'farmer' => $farmer, 'farm' => $farm] = farmExpenseChain();

    $worker = FarmPersonnel::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Peter',
        'role' => 'Herder',
        'farmer_id' => $farmer->id,
        'user_id' => $user->id,
        'status' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson(SALARY_URI, [
        'farm_uuid' => $farm->uuid,
        'farm_personnel_uuid' => $worker->uuid,
        'period' => '2026-08',
        'amount' => 15000,
        'payment_method' => 'cash',
        'date' => '2026-08-01',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.scope', 'general')
        ->assertJsonPath('data.target_label', 'Whole farm');

    $labour = LedgerAccount::where('name', 'Labour')->first();
    $txn = LedgerTransaction::with('entries')->first();
    expect($txn->transactionable_type)->toBe(Farm::class);
    expect($txn->description)->toContain('Peter (Herder)')->toContain('2026-08');
    expect($txn->entries->firstWhere('type', 'debit')->ledger_account_id)->toBe($labour->id);
});

it('lists salaries for a farm', function () {
    ['user' => $user, 'farm' => $farm] = farmExpenseChain();

    $this->actingAs($user, 'sanctum')->postJson(SALARY_URI, [
        'farm_uuid' => $farm->uuid,
        'worker_name' => 'Casual crew',
        'amount' => 3000,
        'payment_method' => 'cash',
        'date' => '2026-08-02',
    ])->assertCreated();

    $this->actingAs($user, 'sanctum')->getJson(SALARY_URI."/list/{$farm->uuid}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
