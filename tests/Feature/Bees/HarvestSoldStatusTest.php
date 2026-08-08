<?php

use App\Models\Core\Production;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const HARVEST_LIST_URI = '/api/v1/farms/farm/bees/harvests/list';
const SOLD_HARVESTS_URI = '/api/v1/farms/farm/bees/harvests';
const SOLD_SALES_URI = '/api/v1/farms/farm/sales';

/** Records a two-hive honey harvest and returns the chain plus its productions. */
function harvestWithTwoHives(): array
{
    $chain = beeTestChain();
    $a = beeTestHive($chain, 1, 'A');
    $b = beeTestHive($chain, 2, 'B');

    test()->actingAs($chain['user'], 'sanctum')->postJson(SOLD_HARVESTS_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'date' => '2026-07-01',
        'products' => [[
            'product' => 'honey',
            'hives' => [
                ['hive_uuid' => $a->uuid, 'quantity' => 4],
                ['hive_uuid' => $b->uuid, 'quantity' => 6],
            ],
        ]],
    ])->assertCreated();

    return [$chain, Production::orderBy('id')->get()];
}

/** Sells one production row from the harvest. */
function sellProduction($chain, Production $production, float $quantity): void
{
    // Sales post to the seeded chart of accounts.
    test()->seed(LedgerAccountsSeeder::class);

    test()->actingAs($chain['user'], 'sanctum')->postJson(SOLD_SALES_URI, [
        'uuid' => (string) Str::orderedUuid(),
        'farm_uuid' => $chain['farm']->uuid,
        'date' => '2026-07-05',
        'payment_method' => 'cash',
        'buyer' => ['name' => 'Honey Buyer', 'phone' => null],
        'items' => [[
            'category' => 'bee_product',
            'product' => 'Honey',
            'quantity' => $quantity,
            'unit' => 'kg',
            'line_total' => $quantity * 500,
            'sellable_type' => 'animal_group',
            'sellable_uuid' => $chain['apiary']->uuid,
            'production_uuid' => $production->uuid,
        ]],
    ])->assertCreated();
}

it('marks a harvest as not sold until something is sold from it', function () {
    [$chain] = harvestWithTwoHives();

    $session = $this->actingAs($chain['user'], 'sanctum')->getJson(HARVEST_LIST_URI)
        ->assertOk()->json('data.0');

    expect($session['sale_status'])->toBe('unsold');
    expect($session['sold_line_count'])->toBe(0);
});

it('marks a harvest part sold when only some of it has been sold', function () {
    [$chain, $productions] = harvestWithTwoHives();

    sellProduction($chain, $productions->first(), 4);

    $session = $this->actingAs($chain['user'], 'sanctum')->getJson(HARVEST_LIST_URI)
        ->assertOk()->json('data.0');

    expect($session['sale_status'])->toBe('part');
    expect($session['sold_line_count'])->toBe(1);
    expect($session['line_count'])->toBe(2);
});

it('marks a harvest sold once every line has been sold', function () {
    [$chain, $productions] = harvestWithTwoHives();

    sellProduction($chain, $productions[0], 4);
    sellProduction($chain, $productions[1], 6);

    $session = $this->actingAs($chain['user'], 'sanctum')->getJson(HARVEST_LIST_URI)
        ->assertOk()->json('data.0');

    expect($session['sale_status'])->toBe('sold');
    expect($session['sale_total'])->toEqual(5000.0);
});

it('treats a voided sale as unsold again', function () {
    [$chain, $productions] = harvestWithTwoHives();

    sellProduction($chain, $productions[0], 4);
    $saleUuid = App\Models\Core\Sale::first()->uuid;

    $this->actingAs($chain['user'], 'sanctum')
        ->postJson(SOLD_SALES_URI."/{$saleUuid}/void")->assertSuccessful();

    $session = $this->actingAs($chain['user'], 'sanctum')->getJson(HARVEST_LIST_URI)
        ->assertOk()->json('data.0');

    expect($session['sale_status'])->toBe('unsold');
});
