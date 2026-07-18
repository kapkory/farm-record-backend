<?php

use App\Models\Core\ApiaryProfile;
use App\Models\Core\Hive;
use App\Models\Core\Production;
use App\Models\Core\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const HARVESTS_URI = '/api/v1/farms/farm/bees/harvests';
const BEE_REPORT_URI = '/api/v1/farms/farm/bees/reports/production';

it('records a honey harvest for selected hives and advances their harvest clock', function () {
    $chain = beeTestChain();
    $a = beeTestHive($chain, 1, 'A');
    $b = beeTestHive($chain, 2, 'B');
    beeTestHive($chain, 3, 'C'); // not harvested

    $response = $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'honey',
            'hives' => [
                ['hive_uuid' => $a->uuid, 'quantity' => 4.5],
                ['hive_uuid' => $b->uuid, 'quantity' => 3],
            ],
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.totals.0.product', 'honey')
        ->assertJsonPath('data.totals.0.unit', 'kg')
        ->assertJsonPath('data.totals.0.quantity', 7.5);

    expect(Production::count())->toBe(2);
    expect((float) $a->productions()->first()->quantity)->toBe(4.5);

    $a->refresh();
    expect($a->last_harvested_at->toDateString())->toBe('2026-07-01')
        ->and($a->next_harvest_due->toDateString())->toBe('2026-09-29'); // +90 days default

    expect(beeTestHiveByCode('C')->next_harvest_due)->toBeNull();
});

it('replays a harvest session without duplicating productions', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A');
    $session = (string) Str::orderedUuid();

    $payload = [
        'uuid' => $session,
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'honey',
            'hives' => [['hive_uuid' => $hive->uuid, 'quantity' => 5]],
        ]],
    ];

    $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, $payload)->assertCreated();
    $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Harvest already saved');

    expect(Production::where('trace_number', $session)->count())->toBe(1);
});

it('does not advance the harvest clock for by-product-only sessions', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A');

    $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'propolis',
            'hives' => [['hive_uuid' => $hive->uuid, 'quantity' => 120]],
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.totals.0.unit', 'g');

    $hive->refresh();
    expect($hive->last_harvested_at)->toBeNull()
        ->and($hive->next_harvest_due)->toBeNull();
});

it('splits a bucket total evenly with the last hive absorbing rounding', function () {
    $chain = beeTestChain();
    $a = beeTestHive($chain, 1, 'A');
    $b = beeTestHive($chain, 2, 'B');
    $c = beeTestHive($chain, 3, 'C');

    $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'honey',
            'total_quantity' => 10,
            'split' => 'even',
            'hive_uuids' => [$a->uuid, $b->uuid, $c->uuid],
        ]],
    ])->assertCreated();

    $quantities = Production::orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all();
    expect($quantities)->toBe([3.33, 3.33, 3.34])
        ->and(array_sum($quantities))->toBe(10.0);
});

it('respects hive and apiary harvest interval overrides', function () {
    $chain = beeTestChain();

    ApiaryProfile::create([
        'animal_group_id' => $chain['apiary']->id,
        'default_harvest_interval_days' => 60,
    ]);

    $profileHive = beeTestHive($chain, 1, 'A');
    $overrideHive = beeTestHive($chain, 2, 'B', ['harvest_interval_days' => 30]);

    $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'honey',
            'hives' => [
                ['hive_uuid' => $profileHive->uuid, 'quantity' => 2],
                ['hive_uuid' => $overrideHive->uuid, 'quantity' => 2],
            ],
        ]],
    ])->assertCreated();

    expect($profileHive->fresh()->next_harvest_due->toDateString())->toBe('2026-08-30')  // +60
        ->and($overrideHive->fresh()->next_harvest_due->toDateString())->toBe('2026-07-31'); // +30
});

it('rejects a harvest containing a foreign hive', function () {
    $chain = beeTestChain();
    $hive = beeTestHive($chain, 1, 'A');
    $outsider = User::factory()->create();

    $this->actingAs($outsider, 'sanctum')->postJson(HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'honey',
            'hives' => [['hive_uuid' => $hive->uuid, 'quantity' => 5]],
        ]],
    ])->assertNotFound();

    expect(Production::count())->toBe(0);
});

it('reports bee production totals per farm', function () {
    $chain = beeTestChain();
    $a = beeTestHive($chain, 1, 'A');
    $b = beeTestHive($chain, 2, 'B');

    $this->actingAs($chain['user'], 'sanctum')->postJson(HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [
            [
                'product' => 'honey',
                'hives' => [
                    ['hive_uuid' => $a->uuid, 'quantity' => 4.5],
                    ['hive_uuid' => $b->uuid, 'quantity' => 3.5],
                ],
            ],
            [
                'product' => 'beeswax',
                'hives' => [['hive_uuid' => $a->uuid, 'quantity' => 1.25]],
            ],
        ],
    ])->assertCreated();

    $rows = $this->actingAs($chain['user'], 'sanctum')
        ->getJson(BEE_REPORT_URI.'?group_by=farm')
        ->assertOk()
        ->json('data.rows');

    $byProduct = collect($rows)->keyBy('product');

    expect($byProduct['honey']['total_quantity'])->toEqual(8)
        ->and($byProduct['honey']['group_label'])->toBe('Bee Farm')
        ->and($byProduct['beeswax']['total_quantity'])->toBe(1.25);
});

it('creates a single reminder task per due hive', function () {
    $chain = beeTestChain();
    beeTestHive($chain, 1, 'A', [
        'last_harvested_at' => now()->subDays(95)->toDateString(),
        'next_harvest_due' => now()->subDays(5)->toDateString(),
    ]);
    beeTestHive($chain, 2, 'B', [
        'next_harvest_due' => now()->addDays(30)->toDateString(),
    ]);

    $this->artisan('bees:flag-due-harvests')->assertExitCode(0);
    $this->artisan('bees:flag-due-harvests')->assertExitCode(0); // idempotent

    expect(Task::count())->toBe(1);

    $task = Task::first();
    expect($task->title)->toContain('Harvest hive A')
        ->and($task->task_status)->toBe(Task::STATUS_PENDING)
        ->and($task->user_id)->toBe($chain['user']->id);
});

function beeTestHiveByCode(string $code): Hive
{
    return Hive::where('code', $code)->firstOrFail();
}
