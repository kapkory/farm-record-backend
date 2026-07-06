<?php

use App\Models\Core\AnimalType;
use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\Core\TreatmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeFarmOwner(string $name = 'Offline Farmer'): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => $name,
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
        'name' => $name."'s Farm",
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    return [$user, $farmer, $farm];
}

function makePlanting(User $user, Farm $farm): Planting
{
    $crop = Crop::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Maize '.Str::random(4),
        'description' => 'Test crop',
    ]);

    return Planting::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);
}

it('creates a farm with a client uuid and replays idempotently', function () {
    [$user] = makeFarmOwner();
    $uuid = (string) Str::uuid();
    $payload = ['uuid' => $uuid, 'name' => 'Offline Farm', 'type' => 'crop'];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    expect(Farm::where('uuid', $uuid)->count())->toBe(1);
});

it('creates a field with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'farm_uuid' => $farm->uuid,
        'name' => 'North Paddock',
        'size' => 2.5,
        'status' => 'active',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/fields', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/fields', $payload);
    $replay->assertOk();

    $this->assertDatabaseCount('fields', 1);
});

it('creates an animal with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'farm_uuid' => $farm->uuid,
        'animal_type_id' => $type->id,
        'name' => 'Bella',
        'gender' => 'female',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('animals', 1);
});

it('rejects a client uuid that belongs to another user', function () {
    [$owner, , $farm] = makeFarmOwner('First Farmer');
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $uuid = (string) Str::uuid();
    $this->actingAs($owner, 'sanctum')->postJson('/api/v1/farms/farm/animals', [
        'uuid' => $uuid,
        'farm_uuid' => $farm->uuid,
        'animal_type_id' => $type->id,
        'name' => 'Bella',
    ])->assertCreated();

    [$intruder, , $intruderFarm] = makeFarmOwner('Second Farmer');
    $this->actingAs($intruder, 'sanctum')->postJson('/api/v1/farms/farm/animals', [
        'uuid' => $uuid,
        'farm_uuid' => $intruderFarm->uuid,
        'animal_type_id' => $type->id,
        'name' => 'Impostor',
    ])->assertStatus(422);

    $this->assertDatabaseCount('animals', 1);
});

it('rejects a malformed client uuid', function () {
    [$user] = makeFarmOwner();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', [
        'uuid' => 'not-a-uuid',
        'title' => 'Check water troughs',
    ])->assertStatus(422);
});

it('creates a breeding with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $damUuid = (string) Str::uuid();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals', [
        'uuid' => $damUuid,
        'farm_uuid' => $farm->uuid,
        'animal_type_id' => $type->id,
        'name' => 'Dam',
        'gender' => 'female',
    ])->assertCreated();

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'dam_id' => $damUuid,
        'sire_type' => 'ai',
        'service_date' => '2026-06-01',
        'ai_bull_name' => 'Champion',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals/breedings', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals/breedings', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('animal_breedings', 1);
});

it('creates an animal event with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $animalUuid = (string) Str::uuid();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals', [
        'uuid' => $animalUuid,
        'farm_uuid' => $farm->uuid,
        'animal_type_id' => $type->id,
        'name' => 'Bella',
    ])->assertCreated();

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'eventable_type' => 'animal',
        'eventable_uuid' => $animalUuid,
        'event_type' => 'weight_check',
        'date' => '2026-06-15',
        'description' => 'Routine weighing',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals/events', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/animals/events', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('animal_events', 1);
});

it('creates a task with a client uuid and replays idempotently', function () {
    [$user] = makeFarmOwner();
    $uuid = (string) Str::uuid();
    $payload = ['uuid' => $uuid, 'title' => 'Fix fence'];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('tasks', 1);
});

it('creates a planting with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $crop = Crop::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Beans',
        'description' => 'Test crop',
    ]);

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'farm_uuid' => $farm->uuid,
        'crop_id' => $crop->id,
        'date_planted' => '2026-04-01',
        'purpose' => 'commercial',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/plantings', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/plantings', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('plantings', 1);
});

it('creates a production with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $planting = makePlanting($user, $farm);

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'productionable_type' => 'planting',
        'productionable_uuid' => $planting->uuid,
        'name' => 'Maize harvest',
        'date' => '2026-07-01',
        'quantity' => 40,
        'unit' => 'Bags',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/productions/store', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/productions/store', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('productions', 1);
});

it('creates a treatment with a client uuid and replays idempotently', function () {
    [$user, , $farm] = makeFarmOwner();
    $planting = makePlanting($user, $farm);
    $treatmentType = TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Foliar Spray',
        'description' => 'Spray treatment',
        'type' => 'crop',
        'status' => 1,
    ]);

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'planting_uuid' => $planting->uuid,
        'treatment_type_id' => $treatmentType->id,
        'details' => 'Applied spray',
        'date' => '2026-05-10',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/crops/treatments', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/crops/treatments', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('treatments', 1);
});

it('does not double-post ledger entries when a transaction create is replayed', function () {
    [$user, $farmer, $farm] = makeFarmOwner();
    $planting = makePlanting($user, $farm);

    $expenseAccount = LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Seeds & Seedlings',
        'slug' => 'seeds-seedlings',
        'type' => 'expense',
        'farmer_id' => $farmer->id,
        'is_system' => false,
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

    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'date' => '2026-05-01',
        'payment_method' => 'cash',
        'description' => 'Seed purchase',
        'transaction_for' => 'planting',
        'type' => 'expense',
        'transaction_uuid' => $planting->uuid,
        'entries' => [
            ['ledger_account_id' => $expenseAccount->id, 'amount' => 1500],
        ],
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/transactions', $payload);
    $first->assertCreated()->assertJsonPath('data.uuid', $uuid);

    $replay = $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/transactions', $payload);
    $replay->assertOk()->assertJsonPath('data.uuid', $uuid);

    $this->assertDatabaseCount('ledger_transactions', 1);
    $this->assertDatabaseCount('ledger_entries', 2);
});

it('still creates with a server uuid when the client sends none', function () {
    [$user] = makeFarmOwner();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'No uuid supplied',
    ]);

    $response->assertCreated();
    expect($response->json('data.uuid'))->toBeString()->not->toBeEmpty();
});
