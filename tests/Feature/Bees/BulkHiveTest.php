<?php

use App\Models\Core\AnimalGroup;
use App\Models\Core\LedgerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const BULK_HIVES_URI = '/api/v1/farms/farm/bees/hives/bulk';

function seedBeeLedgerAccounts($farmer): void
{
    LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Beekeeping Equipment',
        'slug' => 'beekeeping-equipment',
        'type' => 'expense',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);

    LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cash',
        'slug' => 'cash',
        'type' => 'asset',
        'farmer_id' => $farmer->id,
        'is_system' => true,
        'status' => 1,
    ]);
}

it('creates many hives with sequential codes in one request', function () {
    $chain = beeTestChain();

    $response = $this->actingAs($chain['user'], 'sanctum')->postJson(BULK_HIVES_URI, [
        'apiary_uuid' => $chain['apiary']->uuid,
        'count' => 5,
        'hive_type' => 'langstroth',
        'installed_date' => '2026-06-01',
    ]);

    $response->assertCreated()->assertJsonCount(5, 'data');

    $this->assertDatabaseCount('hives', 5);
    foreach (['A', 'B', 'C', 'D', 'E'] as $i => $code) {
        $this->assertDatabaseHas('hives', ['code' => $code, 'sequence' => $i + 1]);
    }
    // No cost given → no ledger posting.
    $this->assertDatabaseCount('ledger_transactions', 0);
});

it('posts a single batch expense to Beekeeping Equipment when a cost is given', function () {
    $chain = beeTestChain();
    seedBeeLedgerAccounts($chain['farmer']);

    $response = $this->actingAs($chain['user'], 'sanctum')->postJson(BULK_HIVES_URI, [
        'apiary_uuid' => $chain['apiary']->uuid,
        'count' => 4,
        'cost' => 12000,
        'installed_date' => '2026-06-01',
    ]);

    $response->assertCreated()->assertJsonCount(4, 'data');

    $this->assertDatabaseCount('hives', 4);
    $this->assertDatabaseCount('ledger_transactions', 1);

    $transaction = App\Models\Core\LedgerTransaction::with('entries.account')->first();
    expect($transaction->transactionable_type)->toBe(AnimalGroup::class);
    expect($transaction->transactionable_id)->toBe($chain['apiary']->id);

    $debit = $transaction->entries->firstWhere('type', 'debit');
    expect($debit->account->name)->toBe('Beekeeping Equipment');
    expect((float) $debit->amount)->toBe(12000.0);
});

it('does not duplicate a batch replayed with the same batch_uuid', function () {
    $chain = beeTestChain();
    seedBeeLedgerAccounts($chain['farmer']);
    $batchUuid = (string) Str::orderedUuid();

    $payload = [
        'batch_uuid' => $batchUuid,
        'apiary_uuid' => $chain['apiary']->uuid,
        'count' => 3,
        'cost' => 9000,
    ];

    $this->actingAs($chain['user'], 'sanctum')->postJson(BULK_HIVES_URI, $payload)->assertCreated();
    $this->actingAs($chain['user'], 'sanctum')->postJson(BULK_HIVES_URI, $payload)->assertSuccessful();

    $this->assertDatabaseCount('hives', 3);
    $this->assertDatabaseCount('ledger_transactions', 1);
});

it('rejects a bulk create on a foreign apiary', function () {
    $chain = beeTestChain();
    $outsider = App\Models\User::factory()->create();

    $this->actingAs($outsider, 'sanctum')->postJson(BULK_HIVES_URI, [
        'apiary_uuid' => $chain['apiary']->uuid,
        'count' => 2,
    ])->assertNotFound();
});
