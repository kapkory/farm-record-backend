<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\LedgerAccount;
use App\Models\Core\LedgerTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const ANIMALS_CRUD_URI = '/api/v1/farms/farm/animals';

/** The seeded expense account animal purchases post to. */
function livestockPurchaseAccount(): LedgerAccount
{
    return LedgerAccount::firstOrCreate(
        ['name' => 'Livestock Purchases', 'farmer_id' => null],
        [
            'uuid' => (string) Str::orderedUuid(),
            'slug' => 'livestock-purchases',
            'type' => 'expense',
            'is_system' => true,
            'status' => 1,
        ]
    );
}

/** A cash account, so the ledger has somewhere to post the contra entry. */
function cashAccount(): LedgerAccount
{
    return LedgerAccount::firstOrCreate(
        ['name' => 'Cash', 'farmer_id' => null],
        [
            'uuid' => (string) Str::orderedUuid(),
            'slug' => 'cash',
            'type' => 'asset',
            'is_system' => true,
            'status' => 1,
        ]
    );
}

function newAnimalPayload(array $chain, array $overrides = []): array
{
    return array_merge([
        'farm_uuid' => $chain['farm']->uuid,
        'animal_type_id' => $chain['type']->id,
        'animal_breed_id' => $chain['breed']->id,
        'name' => 'Nyota',
        'gender' => 'female',
        'acquisition_type' => 'born',
    ], $overrides);
}

// ─── The shape the list expects ──────────────────────────────────────────────

it('returns a newly created animal in the same shape as the livestock list', function () {
    $chain = birthTestChain();

    $response = $this->actingAs($chain['user'], 'sanctum')
        ->postJson(ANIMALS_CRUD_URI, newAnimalPayload($chain))
        ->assertCreated();

    // Every one of these was missing or the wrong type when store returned
    // AnimalResource, which is why a new animal rendered as "Group" with a
    // blank farm, type and breed.
    $response->assertJsonPath('data.tracking_type', 'individual')
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.animal_type.id', $chain['type']->id)
        ->assertJsonPath('data.animal_type.name', $chain['type']->name)
        ->assertJsonPath('data.breed.id', $chain['breed']->id)
        ->assertJsonPath('data.farm.uuid', $chain['farm']->uuid)
        ->assertJsonPath('data.farm_uuid', $chain['farm']->uuid);
});

it('creates a group in the livestock shape too', function () {
    $chain = birthTestChain();

    $this->actingAs($chain['user'], 'sanctum')->postJson('/api/v1/farms/farm/animals/groups', [
        'farm_uuid' => $chain['farm']->uuid,
        'animal_type_id' => $chain['type']->id,
        'name' => 'Broiler batch 2',
        'initial_count' => 50,
        'acquired_date' => now()->subMonth()->toDateString(),
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
    ])->assertCreated()
        ->assertJsonPath('data.tracking_type', 'group')
        ->assertJsonPath('data.group_name', 'Broiler batch 2')
        ->assertJsonPath('data.count', 50)
        ->assertJsonPath('data.farm.uuid', $chain['farm']->uuid);
});

// ─── Purchase price ──────────────────────────────────────────────────────────

it('saves the purchase price instead of silently dropping it', function () {
    $chain = birthTestChain();
    livestockPurchaseAccount();
    cashAccount();

    $uuid = $this->actingAs($chain['user'], 'sanctum')->postJson(ANIMALS_CRUD_URI, newAnimalPayload($chain, [
        'acquisition_type' => 'purchased',
        'acquisition_date' => now()->subWeek()->toDateString(),
        'purchase_price' => 45000,
    ]))->assertCreated()
        ->assertJsonPath('data.purchase_price', 45000.0)
        ->json('data.uuid');

    expect((float) Animal::where('uuid', $uuid)->value('purchase_price'))->toBe(45000.0);
});

it('posts the purchase to the ledger against the animal', function () {
    $chain = birthTestChain();
    livestockPurchaseAccount();
    cashAccount();

    $uuid = $this->actingAs($chain['user'], 'sanctum')->postJson(ANIMALS_CRUD_URI, newAnimalPayload($chain, [
        'acquisition_type' => 'purchased',
        'purchase_price' => 32000,
    ]))->assertCreated()->json('data.uuid');

    $animal = Animal::where('uuid', $uuid)->firstOrFail();

    $transaction = LedgerTransaction::query()
        ->where('transactionable_type', Animal::class)
        ->where('transactionable_id', $animal->id)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->description)->toContain('Nyota');
});

it('does not post an expense for an animal born on the farm', function () {
    $chain = birthTestChain();
    livestockPurchaseAccount();
    cashAccount();

    // A price on a non-purchase is not an acquisition cost.
    $this->actingAs($chain['user'], 'sanctum')->postJson(ANIMALS_CRUD_URI, newAnimalPayload($chain, [
        'acquisition_type' => 'born',
        'purchase_price' => 5000,
    ]))->assertCreated();

    expect(LedgerTransaction::count())->toBe(0);
});

it('still saves the animal when no purchase ledger account exists', function () {
    $chain = birthTestChain();
    // Deliberately no accounts seeded — bookkeeping must never cost the record.

    $this->actingAs($chain['user'], 'sanctum')->postJson(ANIMALS_CRUD_URI, newAnimalPayload($chain, [
        'acquisition_type' => 'purchased',
        'purchase_price' => 12000,
    ]))->assertCreated();

    expect(Animal::where('name', 'Nyota')->exists())->toBeTrue();
});

// ─── Update ──────────────────────────────────────────────────────────────────

it('keeps fields that were not sent when updating', function () {
    $chain = birthTestChain();
    $dam = $chain['dam'];
    $dam->update([
        'gender' => 'female',
        'date_of_birth' => '2024-03-01',
        'notes' => 'Quiet temperament',
    ]);

    // The old update rebuilt the row from `?? null`, so this one-field save
    // wiped sex, date of birth and notes.
    $this->actingAs($chain['user'], 'sanctum')
        ->putJson(ANIMALS_CRUD_URI."/{$dam->uuid}", ['name' => 'Bella Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bella Renamed');

    $fresh = $dam->fresh();
    expect($fresh->gender)->toBe('female')
        ->and($fresh->date_of_birth->toDateString())->toBe('2024-03-01')
        ->and($fresh->notes)->toBe('Quiet temperament');
});

it('applies the fields that were sent', function () {
    $chain = birthTestChain();

    $this->actingAs($chain['user'], 'sanctum')
        ->putJson(ANIMALS_CRUD_URI."/{$chain['dam']->uuid}", [
            'name' => 'Renamed',
            'status' => 'sold',
            'notes' => 'Sold at market',
        ])->assertOk();

    $fresh = $chain['dam']->fresh();
    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->status)->toBe('sold')
        ->and($fresh->notes)->toBe('Sold at market');
});

it('returns the livestock shape from update', function () {
    $chain = birthTestChain();

    $this->actingAs($chain['user'], 'sanctum')
        ->putJson(ANIMALS_CRUD_URI."/{$chain['dam']->uuid}", ['name' => 'Still Individual'])
        ->assertOk()
        ->assertJsonPath('data.tracking_type', 'individual')
        ->assertJsonPath('data.animal_type.id', $chain['type']->id)
        ->assertJsonPath('data.farm.uuid', $chain['farm']->uuid);
});

it('posts a purchase added on a later edit, but only once', function () {
    $chain = birthTestChain();
    livestockPurchaseAccount();
    cashAccount();

    $uri = ANIMALS_CRUD_URI."/{$chain['dam']->uuid}";

    $this->actingAs($chain['user'], 'sanctum')->putJson($uri, [
        'acquisition_type' => 'purchased',
        'purchase_price' => 20000,
    ])->assertOk();

    expect(LedgerTransaction::count())->toBe(1);

    // Editing again must not double-post the same acquisition.
    $this->actingAs($chain['user'], 'sanctum')->putJson($uri, ['notes' => 'Second edit'])->assertOk();

    expect(LedgerTransaction::count())->toBe(1);
});

it('refuses to update an animal on another farm', function () {
    ['dam' => $dam] = birthTestChain();
    ['user' => $outsider] = birthTestChain();

    $this->actingAs($outsider, 'sanctum')
        ->putJson(ANIMALS_CRUD_URI."/{$dam->uuid}", ['name' => 'Stolen'])
        ->assertNotFound();

    expect($dam->fresh()->name)->toBe('Bella');
});

it('rejects a breed that belongs to another animal type', function () {
    $chain = birthTestChain();
    $other = birthTestChain();

    $this->actingAs($chain['user'], 'sanctum')
        ->putJson(ANIMALS_CRUD_URI."/{$chain['dam']->uuid}", ['animal_breed_id' => $other['breed']->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('animal_breed_id');
});

// ─── Reaching a grouped animal ───────────────────────────────────────────────

it('opens an individual animal that belongs to a group', function () {
    $chain = birthTestChain();

    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $chain['farm']->id,
        'farmer_id' => $chain['farmer']->id,
        'animal_type_id' => $chain['type']->id,
        'name' => 'Milking herd',
        'initial_count' => 5,
        'current_count' => 5,
        'acquired_date' => now()->subYear()->toDateString(),
        'acquisition_type' => 'purchased',
        'purpose' => 'commercial',
        'user_id' => $chain['user']->id,
        'status' => 1,
    ]);
    $chain['dam']->update(['animal_group_id' => $group->id]);

    // show() used to filter to standalone animals, so a grouped animal 404'd
    // and could be neither viewed nor edited.
    $this->actingAs($chain['user'], 'sanctum')
        ->getJson("/api/v1/farms/farm/animals/livestocks/{$chain['dam']->uuid}")
        ->assertOk()
        ->assertJsonPath('data.tracking_type', 'individual')
        ->assertJsonPath('data.animal_group.uuid', $group->uuid);
});
