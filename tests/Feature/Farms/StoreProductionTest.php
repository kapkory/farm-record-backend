<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalType;
use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use App\Models\Core\Production;
use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
const PRODUCTIONS_BASE_URI = '/api/v1/farms/farm/productions/store';
it('stores a production for a planting using the submitted form payload', function () {
    $user = User::factory()->create();
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Harvest Demo Farmer',
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
        'name' => 'Harvest Farm',
        'location' => 'Nakuru',
        'type' => 'crop',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);
    $crop = Crop::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Maize',
        'description' => 'Maize crop',
    ]);
    $planting = Planting::create([
        'uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);
    $response = $this->actingAs($user, 'sanctum')->postJson(PRODUCTIONS_BASE_URI, [
        'productionable_type' => 'planting',
        'productionable_uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'name' => 'Maize',
        'date' => '2026-03-20 00:00:00',
        'trace_number' => null,
        'quantity' => 3000,
        'unit' => 'Bags',
        'grade' => null,
        'notes' => 'Good quaility',
    ]);
    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.productionable_type', 'planting')
        ->assertJsonPath('data.productionable_id', $planting->id)
        ->assertJsonPath('data.name', 'Maize')
        ->assertJsonPath('data.date', '2026-03-20')
        ->assertJsonPath('data.quantity', 3000)
        ->assertJsonPath('data.unit', 'Bags')
        ->assertJsonPath('data.notes', 'Good quaility');
    $this->assertDatabaseHas('productions', [
        'productionable_type' => Planting::class,
        'productionable_id' => $planting->id,
        'name' => 'Maize',
        'date' => '2026-03-20 00:00:00',
        'quantity' => 3000,
        'unit' => 'Bags',
        'user_id' => $user->id,
    ]);
});
it('requires the business-required production fields', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user, 'sanctum')->postJson(PRODUCTIONS_BASE_URI, [
        'productionable_type' => 'planting',
        'productionable_uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'trace_number' => null,
        'grade' => null,
        'notes' => 'Good quaility',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'name',
            'date',
            'unit',
        ]);
});

it('stores a milk production for an individual animal', function () {
    $user = User::factory()->create();
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Dairy Farmer',
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
        'name' => 'Dairy Farm',
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);
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

    $response = $this->actingAs($user, 'sanctum')->postJson(PRODUCTIONS_BASE_URI, [
        'productionable_type' => 'animal',
        'productionable_uuid' => $animal->uuid,
        'name' => 'Milk',
        'date' => '2026-07-19',
        'quantity' => 12.5,
        'unit' => 'litres',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.productionable_type', 'animal')
        ->assertJsonPath('data.name', 'Milk')
        ->assertJsonPath('data.quantity', 12.5);

    $this->assertDatabaseHas('productions', [
        'productionable_type' => Animal::class,
        'productionable_id' => $animal->id,
        'name' => 'Milk',
    ]);
});

it('lists recent unlinked productions filtered by product', function () {
    test()->seed(LedgerAccountsSeeder::class);

    $user = User::factory()->create();
    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Link Farmer',
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
        'name' => 'Link Farm',
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);
    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Bees',
        'category' => 'apiculture',
        'tracking_mode' => 'group_only',
    ]);
    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'name' => 'Apiary',
        'initial_count' => 10,
        'current_count' => 10,
        'acquired_date' => '2026-01-01',
        'user_id' => $user->id,
    ]);

    $makeProduction = fn (string $name, string $date) => Production::create([
        'uuid' => (string) Str::orderedUuid(),
        'productionable_type' => AnimalGroup::class,
        'productionable_id' => $group->id,
        'name' => $name,
        'date' => $date,
        'quantity' => 10,
        'unit' => 'kg',
        'user_id' => $user->id,
    ]);

    $honey = $makeProduction('honey', '2026-07-15');
    $combHoney = $makeProduction('comb_honey', '2026-07-14');
    $soldHoney = $makeProduction('honey', '2026-07-10');
    $makeProduction('Eggs', '2026-07-16');

    // Selling from one collection links it and removes it from the picker.
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/farms/farm/sales', [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-11',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'bee_product', 'product' => 'honey', 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 800, 'production_uuid' => $soldHoney->uuid],
        ],
    ])->assertCreated();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/farms/farm/productions/unlinked?product=honey')
        ->assertOk();

    $uuids = collect($response->json('data'))->pluck('uuid');
    expect($uuids)->toContain($honey->uuid)
        ->and($uuids)->not->toContain($soldHoney->uuid)
        // Substring match: 'honey' also offers comb honey collections.
        ->and($uuids)->toContain($combHoney->uuid)
        ->and(collect($response->json('data'))->pluck('name'))->not->toContain('Eggs');

    // 'Comb honey' (space) matches the underscored collection name.
    $combResponse = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/farms/farm/productions/unlinked?product='.urlencode('Comb honey'))
        ->assertOk();
    expect(collect($combResponse->json('data'))->pluck('uuid'))->toContain($combHoney->uuid);

    // Hive collections: bee harvests store the morph alias ('hive'), and
    // each picker row carries the hive code so two harvests are tellable apart.
    $hive = Hive::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_group_id' => $group->id,
        'sequence' => 1,
        'code' => 'A1',
        'user_id' => $user->id,
    ]);
    Production::create([
        'uuid' => (string) Str::orderedUuid(),
        'productionable_type' => $hive->getMorphClass(), // 'hive' alias, as HiveHarvestService stores it
        'productionable_id' => $hive->id,
        'name' => 'honey',
        'date' => '2026-07-17',
        'quantity' => 4,
        'unit' => 'kg',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/farms/farm/productions/unlinked?product=honey&sellable_type=hive&sellable_uuid='.$hive->uuid)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.source_label', 'Hive A1');

    // Another farmer sees none of it.
    $stranger = User::factory()->create();
    $strangerFarmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Stranger',
        'type' => 'individual',
        'status' => 1,
    ]);
    FarmerUser::create([
        'farmer_id' => $strangerFarmer->id,
        'user_id' => $stranger->id,
        'role' => 'owner',
        'status' => 1,
    ]);
    $this->actingAs($stranger, 'sanctum')
        ->getJson('/api/v1/farms/farm/productions/unlinked?product=honey')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
