<?php

use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Planting;
use App\Models\Core\Treatment;
use App\Models\Core\TreatmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const TREATMENTS_BASE_URI = '/api/v1/farms/farm/crops/treatments';

it('stores a treatment for a planting', function () {
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
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'Treatment Farm',
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
        'uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);

    $treatmentType = TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Foliar Spray',
        'description' => 'Spray treatment',
        'type' => 'crop',
        'status' => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson(TREATMENTS_BASE_URI, [
        'planting_uuid' => 'a151014b-56e1-4376-9f42-fcf8451b76d5',
        'treatment_type_id' => $treatmentType->id,
        'details' => 'Applied spray on leaves',
        'date' => '2026-03-22',
        'notes' => 'Observed mild pest pressure',
        'retreat_date' => '2026-03-29',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.treatment_type_id', $treatmentType->id)
        ->assertJsonPath('data.farm_id', $farm->id)
        ->assertJsonPath('data.details', 'Applied spray on leaves');

    $this->assertDatabaseHas('treatments', [
        'treatment_type_id' => $treatmentType->id,
        'farm_id' => $farm->id,
        'treatmentable_type' => App\Models\Core\Planting::class,
        'treatmentable_id' => $planting->id,
        'details' => 'Applied spray on leaves',
        'user_id' => $user->id,
    ]);
});

it('lists treatments for a planting', function () {
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
        'uuid' => (string) Str::orderedUuid(),
        'farmer_id' => $farmer->id,
        'name' => 'Treatment Farm',
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
        'uuid' => '2f06e4a0-1f5e-4ad1-b6d1-4f65e1c32d11',
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);

    $treatmentType = TreatmentType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Fungicide',
        'description' => 'Fungicide treatment',
        'type' => 'crop',
        'status' => 1,
    ]);

    Treatment::create([
        'uuid' => (string) Str::orderedUuid(),
        'treatment_type_id' => $treatmentType->id,
        'farm_id' => $farm->id,
        'details' => 'Preventive fungicide applied',
        'treatmentable_type' => App\Models\Core\Planting::class,
        'treatmentable_id' => $planting->id,
        'date' => '2026-03-22',
        'notes' => 'Early morning application',
        'retreat_date' => '2026-03-30',
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson(TREATMENTS_BASE_URI.'/list/2f06e4a0-1f5e-4ad1-b6d1-4f65e1c32d11');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.treatment_type', 'Fungicide')
        ->assertJsonPath('data.0.type', 'crop')
        ->assertJsonPath('data.0.details', 'Preventive fungicide applied')
        ->assertJsonPath('data.0.notes', 'Early morning application')
        ->assertJsonPath('data.0.retreat_date', '2026-03-30T00:00:00.000000Z');
});
