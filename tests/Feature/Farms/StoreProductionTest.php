<?php
use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Planting;
use App\Models\User;
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
        'date' => '2026-03-20',
        'trace_number' => null,
        'quantity' => 3000,
        'unit' => 'Bags',
        'grade' => null,
        'notes' => 'Good quaility',
    ]);
    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.productionable_type', App\Models\Core\Planting::class)
        ->assertJsonPath('data.productionable_id', $planting->id)
        ->assertJsonPath('data.name', 'Maize')
        ->assertJsonPath('data.date', '2026-03-20')
        ->assertJsonPath('data.quantity', 3000)
        ->assertJsonPath('data.unit', 'Bags')
        ->assertJsonPath('data.notes', 'Good quaility');
    $this->assertDatabaseHas('productions', [
        'productionable_type' => App\Models\Core\Planting::class,
        'productionable_id' => $planting->id,
        'name' => 'Maize',
        'date' => '2026-03-20',
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
