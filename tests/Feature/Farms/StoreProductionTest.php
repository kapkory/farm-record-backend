<?php
use App\Models\Core\Crop;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\Planting;
use App\Models\Core\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
uses(RefreshDatabase::class);
const PRODUCTIONS_BASE_URI = '/api/v1/farms/farm/productions';
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
    $response = $this->actingAs($user, 'sanctum')->postJson(PRODUCTIONS_BASE_URI.'/store', [
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
        ->assertJsonPath('data.productionable_type', 'planting')
        ->assertJsonPath('data.productionable_id', $planting->id)
        ->assertJsonPath('data.productionable_uuid', $planting->uuid)
        ->assertJsonPath('data.name', 'Maize')
        ->assertJsonPath('data.date', '2026-03-20')
        ->assertJsonPath('data.quantity', 3000)
        ->assertJsonPath('data.unit', 'Bags')
        ->assertJsonPath('data.notes', 'Good quaility');
    $this->assertDatabaseHas('productions', [
        'productionable_type' => App\Models\Core\Planting::class,
        'productionable_id' => $planting->id,
        'name' => 'Maize',
        'date' => '2026-03-20 00:00:00',
        'quantity' => 3000,
        'unit' => 'Bags',
        'user_id' => $user->id,
    ]);
});
it('lists harvests for a planting using the production resource shape', function () {
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
        'uuid' => '2f06e4a0-1f5e-4ad1-b6d1-4f65e1c32d11',
        'farm_id' => $farm->id,
        'crop_id' => $crop->id,
        'date_planted' => '2026-03-01',
        'purpose' => 'commercial',
        'user_id' => $user->id,
    ]);
    Production::create([
        'uuid' => '9ddaf3f2-e516-4fb2-a52f-8ef8ad5c0a93',
        'productionable_type' => App\Models\Core\Planting::class,
        'productionable_id' => $planting->id,
        'name' => 'Maize',
        'quantity' => 18,
        'date' => '2026-03-22',
        'unit' => 'Bags',
        'user_id' => $user->id,
        'trace_number' => 'HZV-2026-002',
        'grade' => 'Grade B',
        'notes' => 'Second picking',
    ]);
    $response = $this->actingAs($user, 'sanctum')->getJson(PRODUCTIONS_BASE_URI.'/2f06e4a0-1f5e-4ad1-b6d1-4f65e1c32d11/harvests');
    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 1)
        ->assertJsonPath('data.0.uuid', '9ddaf3f2-e516-4fb2-a52f-8ef8ad5c0a93')
        ->assertJsonPath('data.0.productionable_type', 'planting')
        ->assertJsonPath('data.0.productionable_uuid', '2f06e4a0-1f5e-4ad1-b6d1-4f65e1c32d11')
        ->assertJsonPath('data.0.name', 'Maize')
        ->assertJsonPath('data.0.quantity', 18)
        ->assertJsonPath('data.0.date', '2026-03-22')
        ->assertJsonPath('data.0.unit', 'Bags')
        ->assertJsonPath('data.0.trace_number', 'HZV-2026-002')
        ->assertJsonPath('data.0.grade', 'Grade B')
        ->assertJsonPath('data.0.notes', 'Second picking');
});
it('requires the business-required production fields', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user, 'sanctum')->postJson(PRODUCTIONS_BASE_URI.'/store', [
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
            'quantity',
            'unit',
        ]);
});
