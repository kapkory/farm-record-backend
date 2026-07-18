<?php

use App\Models\Core\ApiaryProfile;
use App\Services\Bees\HiveNamingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps sequences to alpha codes across cycle boundaries', function (int $sequence, string $expected) {
    $service = new HiveNamingService;

    expect($service->codeFor($sequence, null))->toBe($expected);
})->with([
    [1, 'A'],
    [2, 'B'],
    [26, 'Z'],
    [27, '1A'],
    [52, '1Z'],
    [53, '2A'],
    [78, '2Z'],
    [79, '3A'],
]);

it('prepends the custom prefix with a dash', function () {
    $service = new HiveNamingService;

    expect($service->codeFor(1, 'kb'))->toBe('KB-A')
        ->and($service->codeFor(27, 'KB'))->toBe('KB-1A')
        ->and($service->codeFor(7, 'KB', ApiaryProfile::SCHEME_NUMERIC))->toBe('KB-7')
        ->and($service->codeFor(7, null, ApiaryProfile::SCHEME_NUMERIC))->toBe('7');
});

it('allocates sequential codes and advances the apiary counter', function () {
    $chain = beeTestChain();
    $service = new HiveNamingService;

    $first = $service->allocate($chain['apiary']);
    $second = $service->allocate($chain['apiary']);

    expect($first)->toBe(['sequence' => 1, 'code' => 'A'])
        ->and($second)->toBe(['sequence' => 2, 'code' => 'B']);

    $this->assertDatabaseHas('apiary_profiles', [
        'animal_group_id' => $chain['apiary']->id,
        'next_sequence' => 3,
    ]);
});

it('honors an existing prefix and scheme when allocating', function () {
    $chain = beeTestChain();

    ApiaryProfile::create([
        'animal_group_id' => $chain['apiary']->id,
        'naming_prefix' => 'KB',
        'naming_scheme' => ApiaryProfile::SCHEME_ALPHA,
        'next_sequence' => 26,
    ]);

    $service = new HiveNamingService;

    expect($service->allocate($chain['apiary'])['code'])->toBe('KB-Z')
        ->and($service->allocate($chain['apiary'])['code'])->toBe('KB-1A');
});
