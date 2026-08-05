<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const HIVE_STATUS_URI = '/api/v1/farms/farm/bees/hives';

it('stamps when bees leave and when a colony returns', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A', ['occupancy' => 'occupied', 'installed_date' => '2026-01-01']);

    // Bees abscond — vacated_at is stamped, has_bees flips false.
    $left = $this->actingAs($chain['user'], 'sanctum')->putJson(HIVE_STATUS_URI."/{$hive->uuid}", [
        'occupancy' => 'absconded',
        'last_inspected_at' => '2026-05-10',
    ]);

    $left->assertOk()
        ->assertJsonPath('data.occupancy', 'absconded')
        ->assertJsonPath('data.has_bees', false)
        ->assertJsonPath('data.vacated_at', '2026-05-10');

    $hive->refresh();
    expect($hive->occupancy)->toBe('absconded');
    expect($hive->vacated_at?->toDateString())->toBe('2026-05-10');

    // A new colony moves in — colonized_at is stamped and vacated_at cleared.
    $back = $this->actingAs($chain['user'], 'sanctum')->putJson(HIVE_STATUS_URI."/{$hive->uuid}", [
        'occupancy' => 'occupied',
        'last_inspected_at' => '2026-06-01',
    ]);

    $back->assertOk()
        ->assertJsonPath('data.occupancy', 'occupied')
        ->assertJsonPath('data.has_bees', true)
        ->assertJsonPath('data.colonized_at', '2026-06-01')
        ->assertJsonPath('data.vacated_at', null);
});

it('defaults the colony date to today when none is given', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A', ['occupancy' => 'occupied']);

    $this->actingAs($chain['user'], 'sanctum')->putJson(HIVE_STATUS_URI."/{$hive->uuid}", [
        'occupancy' => 'dead',
    ])->assertOk()->assertJsonPath('data.vacated_at', now()->toDateString());
});
