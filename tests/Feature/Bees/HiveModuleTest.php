<?php

use App\Models\Core\Hive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const HIVES_URI = '/api/v1/farms/farm/bees/hives';
const APIARIES_URI = '/api/v1/farms/farm/bees/apiaries';

it('creates hives with auto-assigned sequential codes', function () {
    $chain = beeTestChain();

    $first = $this->actingAs($chain['user'], 'sanctum')->postJson(HIVES_URI, [
        'farm_uuid' => $chain['farm']->uuid,
        'apiary_uuid' => $chain['apiary']->uuid,
        'hive_type' => 'langstroth',
        'installed_date' => '2026-06-01',
    ]);

    $second = $this->actingAs($chain['user'], 'sanctum')->postJson(HIVES_URI, [
        'farm_uuid' => $chain['farm']->uuid,
        'apiary_uuid' => $chain['apiary']->uuid,
    ]);

    $first->assertCreated()
        ->assertJsonPath('data.code', 'A')
        ->assertJsonPath('data.occupancy', 'occupied')
        ->assertJsonPath('data.apiary_uuid', $chain['apiary']->uuid);
    $second->assertCreated()->assertJsonPath('data.code', 'B');

    $this->assertDatabaseHas('hives', ['code' => 'A', 'sequence' => 1]);
    $this->assertDatabaseHas('hives', ['code' => 'B', 'sequence' => 2]);
});

it('uses the apiary naming profile for new hive codes', function () {
    $chain = beeTestChain();

    $this->actingAs($chain['user'], 'sanctum')
        ->postJson(APIARIES_URI."/{$chain['apiary']->uuid}/profile", [
            'naming_prefix' => 'KB',
            'start_letter' => 'H',
        ])
        ->assertOk()
        ->assertJsonPath('data.next_code_preview', 'KB-H');

    $this->actingAs($chain['user'], 'sanctum')->postJson(HIVES_URI, [
        'farm_uuid' => $chain['farm']->uuid,
        'apiary_uuid' => $chain['apiary']->uuid,
    ])->assertCreated()->assertJsonPath('data.code', 'KB-H');
});

it('ignores a start letter once hives exist', function () {
    $chain = beeTestChain();
    beeTestHive($chain, 1, 'A');

    $this->actingAs($chain['user'], 'sanctum')
        ->postJson(APIARIES_URI."/{$chain['apiary']->uuid}/profile", ['start_letter' => 'M'])
        ->assertOk()
        ->assertJsonPath('data.next_sequence', 1);
});

it('returns 404 when creating a hive on a foreign farm', function () {
    $chain = beeTestChain();
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')->postJson(HIVES_URI, [
        'farm_uuid' => $chain['farm']->uuid,
        'apiary_uuid' => $chain['apiary']->uuid,
    ])->assertNotFound();
});

it('replays a hive create with the same client uuid without duplicating', function () {
    $chain = beeTestChain();
    $clientUuid = (string) Str::orderedUuid();

    $payload = [
        'uuid' => $clientUuid,
        'farm_uuid' => $chain['farm']->uuid,
        'apiary_uuid' => $chain['apiary']->uuid,
    ];

    $this->actingAs($chain['user'], 'sanctum')->postJson(HIVES_URI, $payload)->assertCreated();
    $this->actingAs($chain['user'], 'sanctum')->postJson(HIVES_URI, $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Hive already saved');

    expect(Hive::where('uuid', $clientUuid)->count())->toBe(1);
});

it('updates hive fields but never the code', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A');

    $this->actingAs($chain['user'], 'sanctum')->putJson(HIVES_URI."/{$hive->uuid}", [
        'name' => 'Queen Bess',
        'occupancy' => 'empty',
        'code' => 'HACKED',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Queen Bess')
        ->assertJsonPath('data.code', 'A');

    $this->assertDatabaseHas('hives', ['id' => $hive->id, 'code' => 'A', 'occupancy' => 'empty']);
});

it('keeps the apiary current_count equal to occupied hives', function () {
    $chain = beeTestChain();
    $a = beeTestHive($chain, 1, 'A');
    beeTestHive($chain, 2, 'B');

    expect($chain['apiary']->fresh()->current_count)->toBe(2);

    $a->update(['occupancy' => Hive::OCCUPANCY_ABSCONDED]);
    expect($chain['apiary']->fresh()->current_count)->toBe(1);

    $a->delete();
    expect($chain['apiary']->fresh()->current_count)->toBe(1);
});

it('lists hives with readiness information', function () {
    $chain = beeTestChain();
    beeTestHive($chain, 1, 'A', [
        'last_harvested_at' => now()->subDays(100)->toDateString(),
        'next_harvest_due' => now()->subDays(10)->toDateString(),
    ]);
    beeTestHive($chain, 2, 'B', [
        'last_harvested_at' => now()->subDays(10)->toDateString(),
        'next_harvest_due' => now()->addDays(80)->toDateString(),
    ]);
    beeTestHive($chain, 3, 'C');

    $response = $this->actingAs($chain['user'], 'sanctum')
        ->getJson(HIVES_URI.'/list?apiary='.$chain['apiary']->uuid)
        ->assertOk();

    $byCode = collect($response->json('data'))->keyBy('code');

    expect($byCode['A']['harvest_status'])->toBe('ready')
        ->and($byCode['A']['days_remaining'])->toBe(0)
        ->and($byCode['B']['harvest_status'])->toBe('waiting')
        ->and($byCode['B']['days_remaining'])->toBe(80)
        ->and($byCode['C']['harvest_status'])->toBe('unknown');
});

it('hides other farmers hives from list and show', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A');
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')->getJson(HIVES_URI.'/list')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($outsider, 'sanctum')->getJson(HIVES_URI."/{$hive->uuid}")->assertNotFound();
});
