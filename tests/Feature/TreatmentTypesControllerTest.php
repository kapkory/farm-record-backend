<?php

use App\Models\Core\TreatmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const TREATMENT_TYPES_BASE_URI = '/api/v1/settings/crops/treatment-types';

it('creates a treatment type with a valid domain type', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson(TREATMENT_TYPES_BASE_URI, [
        'name' => 'Foliar Spray',
        'description' => 'Used on crops after emergence',
        'type' => 'crop',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Foliar Spray')
        ->assertJsonPath('data.type', 'crop');

    $this->assertDatabaseHas('treatment_types', [
        'name' => 'Foliar Spray',
        'type' => 'crop',
    ]);
});

it('lists treatment types', function () {
    $user = User::factory()->create();

    TreatmentType::create([
        'uuid' => (string) \Illuminate\Support\Str::orderedUuid(),
        'name' => 'Vaccination',
        'description' => 'Livestock vaccine treatment',
        'type' => 'livestock',
        'status' => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson(TREATMENT_TYPES_BASE_URI.'/list');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Vaccination')
        ->assertJsonPath('data.0.type', 'livestock');
});

it('deletes a treatment type by uuid', function () {
    $user = User::factory()->create();

    $treatmentType = TreatmentType::create([
        'uuid' => (string) \Illuminate\Support\Str::orderedUuid(),
        'name' => 'Sanitation',
        'description' => 'General sanitation process',
        'type' => 'general',
        'status' => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')->deleteJson(TREATMENT_TYPES_BASE_URI.'/'.$treatmentType->uuid);

    $response->assertOk()
        ->assertJsonPath('status', 'success');

    expect(TreatmentType::where('uuid', $treatmentType->uuid)->exists())->toBeFalse();
});

