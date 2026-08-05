<?php

use App\Models\Core\AnimalGroup;
use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\LedgerAccount;
use App\Models\Core\Planting;
use App\Models\Core\Treatment;
use App\Models\Core\TreatmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const CROP_TREATMENTS_BASE_URI = '/api/v1/farms/farm/crops/treatments';

it('stores a treatment and records its cost in the ledger when record_expense is true', function () {
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Treatment Farmer',
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
        'uuid' => 'a0970a7d-0a0e-482b-b3aa-a55044c321c8',
        'farmer_id' => $farmer->id,
        'name' => 'Treatment Farm',
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

    $treatmentType = TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Spraying',
        'description' => 'Crop spraying',
        'type' => 'crop',
        'status' => 1,
    ]);

    LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Fertilizer & Chemicals',
        'slug' => 'fertilizer-chemicals',
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

    $response = $this->actingAs($user, 'sanctum')->postJson(CROP_TREATMENTS_BASE_URI, [
        'date' => '2026-03-22',
        'details' => 'spraying fall army worms',
        'expense_amount' => 34000,
        'farm_id' => 'a0970a7d-0a0e-482b-b3aa-a55044c321c8',
        'model' => 'planting',
        'notes' => 'sample treatemnt',
        'planting_uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'record_expense' => true,
        'retreat_date' => '2026-03-29',
        'treatment_type_id' => $treatmentType->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.farm_id', $farm->id)
        ->assertJsonPath('data.details', 'spraying fall army worms');

    $this->assertDatabaseHas('treatments', [
        'farm_id' => $farm->id,
        'treatmentable_type' => App\Models\Core\Planting::class,
        'treatmentable_id' => $planting->id,
        'details' => 'spraying fall army worms',
    ]);

    $this->assertDatabaseCount('ledger_transactions', 1);
    $this->assertDatabaseHas('ledger_entries', [
        'amount' => '34000.00',
    ]);
});

it('records a livestock treatment cost against the Veterinary & Medicine account', function () {
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Livestock Farmer',
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
        'name' => 'Livestock Farm',
        'location' => 'Eldoret',
        'type' => 'animal',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    $animalType = App\Models\Core\AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Layers',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $animalType->id,
        'name' => 'Layers Batch 1',
        'initial_count' => 200,
        'current_count' => 200,
        'acquired_date' => '2026-01-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
        'status' => 1,
    ]);

    $treatmentType = TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Newcastle Vaccine',
        'description' => 'Livestock vaccine',
        'type' => 'livestock',
        'status' => 1,
    ]);

    // The seeded chart of accounts uses these exact names; the recorder must
    // match them or a livestock treatment expense finds no account and 422s.
    LedgerAccount::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Veterinary & Medicine',
        'slug' => 'veterinary-medicine',
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

    $response = $this->actingAs($user, 'sanctum')->postJson(CROP_TREATMENTS_BASE_URI, [
        'date' => '2026-03-22',
        'details' => 'Newcastle vaccination',
        'expense_amount' => 5000,
        'farm_id' => $farm->uuid,
        'model' => 'animal_group',
        'animal_group_uuid' => $group->uuid,
        'record_expense' => true,
        'treatment_type_id' => $treatmentType->id,
    ]);

    $response->assertCreated();

    $this->assertDatabaseCount('ledger_transactions', 1);
    $this->assertDatabaseHas('ledger_transactions', [
        'transactionable_type' => AnimalGroup::class,
        'transactionable_id' => $group->id,
    ]);
    $this->assertDatabaseHas('ledger_entries', [
        'amount' => '5000.00',
    ]);
});

it('requires expense_amount when record_expense is true', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson(CROP_TREATMENTS_BASE_URI, [
        'date' => '2026-03-22',
        'details' => 'spraying fall army worms',
        'planting_uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'record_expense' => true,
        'retreat_date' => '2026-03-29',
        'treatment_type_id' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['expense_amount']);
});

