<?php

use App\Models\Core\AnimalBreed;
use App\Models\Core\AnimalType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const ANIMAL_BREEDS_LIST_URI = '/api/v1/settings/animals/animal-breeds/list';

it('lists animal breeds using a resource with animal type id and name', function () {
    $user = User::factory()->create();

    $cattle = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
        'count_label' => 'heads',
        'status' => 1,
    ]);

    $goats = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Goats',
        'category' => 'livestock',
        'tracking_mode' => 'both',
        'count_label' => 'animals',
        'status' => 1,
    ]);

    $breed = AnimalBreed::create([
        'uuid' => (string) Str::orderedUuid(),
        'animal_type_id' => $cattle->id,
        'name' => 'Holstein',
        'purpose' => 'dairy',
        'gestation_days' => 283,
        'status' => 1,
    ]);

    AnimalBreed::create([
        'uuid' => (string) Str::orderedUuid(),
        'animal_type_id' => $goats->id,
        'name' => 'Boer',
        'purpose' => 'meat',
        'status' => 1,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson(ANIMAL_BREEDS_LIST_URI.'?animal_type_id='.$cattle->id)
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $breed->id)
        ->assertJsonPath('data.0.name', 'Holstein')
        ->assertJsonPath('data.0.animal_type_id', $cattle->id)
        ->assertJsonPath('data.0.animal_type.id', $cattle->id)
        ->assertJsonPath('data.0.animal_type.name', 'Cattle');
});

