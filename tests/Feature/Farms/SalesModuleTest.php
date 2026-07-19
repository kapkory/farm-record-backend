<?php

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\AnimalType;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Models\Core\FarmerUser;
use App\Models\Core\LedgerEntry;
use App\Models\Core\LedgerTransaction;
use App\Models\Core\Production;
use App\Models\Core\Sale;
use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const SALES_BASE_URI = '/api/v1/farms/farm/sales';

function salesTestContext(): array
{
    $user = User::factory()->create();

    $farmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Sales Demo Farmer',
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
        'name' => 'Sales Farm',
        'location' => 'Nakuru',
        'type' => 'mixed',
        'ownership_type' => 'owned',
        'status' => 1,
    ]);

    test()->seed(LedgerAccountsSeeder::class);

    return [$user, $farmer, $farm];
}

it('records a cash honey sale with balanced ledger entries', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $response = $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-10',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'bee_product', 'product' => 'honey', 'quantity' => 12, 'unit' => 'kg', 'unit_price' => 800],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.amount_total', 9600)
        ->assertJsonPath('data.amount_paid', 9600)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.items.0.product', 'honey')
        ->assertJsonPath('data.items.0.line_total', 9600);

    $sale = Sale::first();
    $transaction = $sale->ledgerTransactions()->first();

    expect($transaction)->not->toBeNull();

    $entries = LedgerEntry::where('ledger_transaction_id', $transaction->id)->with('account')->get();
    expect($entries)->toHaveCount(2);

    $credit = $entries->firstWhere('type', 'credit');
    $debit = $entries->firstWhere('type', 'debit');
    expect((float) $credit->amount)->toBe(9600.0)
        ->and((float) $debit->amount)->toBe(9600.0)
        ->and($credit->account->name)->toBe('Milk, Eggs & Honey Sales')
        ->and($debit->account->name)->toBe('Cash');
});

it('computes the unit price when the farmer only enters a total', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $response = $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-10',
        'payment_method' => 'mobile_money',
        'items' => [
            ['category' => 'animal_product', 'product' => 'milk', 'quantity' => 25, 'unit' => 'litres', 'line_total' => 1500],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.items.0.unit_price', 60)
        ->assertJsonPath('data.amount_total', 1500);
});

it('replays an offline create without posting the money twice', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $payload = [
        'uuid' => 'b251014b-56e1-4376-9f42-fcf8451b76d5',
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-10',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'crop', 'product' => 'maize', 'quantity' => 10, 'unit' => 'bags', 'unit_price' => 3000],
        ],
    ];

    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, $payload)->assertCreated();
    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Sale already recorded');

    expect(Sale::count())->toBe(1)
        ->and(LedgerTransaction::count())->toBe(1);
});

it('marks a credit sale as owed against Accounts Receivable and settles it with a payment', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-10',
        'payment_method' => 'credit',
        'buyer' => ['name' => 'Mama Njeri', 'phone' => '+254700000001'],
        'items' => [
            ['category' => 'animal_product', 'product' => 'milk', 'quantity' => 30, 'unit' => 'litres', 'unit_price' => 55],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'owed')
        ->assertJsonPath('data.amount_paid', 0)
        ->assertJsonPath('data.balance_due', 1650)
        ->assertJsonPath('data.buyer.name', 'Mama Njeri');

    $sale = Sale::first();
    $income = $sale->ledgerTransaction()->first();
    $arEntry = LedgerEntry::where('ledger_transaction_id', $income->id)
        ->where('type', 'debit')->with('account')->first();
    expect($arEntry->account->name)->toBe('Accounts Receivable');

    // Partial payment.
    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI."/{$sale->uuid}/payments", [
        'date' => '2026-07-15',
        'amount' => 1000,
        'payment_method' => 'mobile_money',
    ])->assertCreated()
        ->assertJsonPath('data.sale.status', 'partial')
        ->assertJsonPath('data.sale.amount_paid', 1000);

    // Overpayment is rejected.
    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI."/{$sale->uuid}/payments", [
        'date' => '2026-07-16',
        'amount' => 5000,
        'payment_method' => 'cash',
    ])->assertStatus(422);

    // Settling payment.
    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI."/{$sale->uuid}/payments", [
        'date' => '2026-07-16',
        'amount' => 650,
        'payment_method' => 'cash',
    ])->assertCreated()
        ->assertJsonPath('data.sale.status', 'paid')
        ->assertJsonPath('data.sale.balance_due', 0);

    // The payment transfers debit cash/mobile money and credit AR.
    $paymentTxns = $sale->ledgerTransactions()->where('id', '!=', $income->id)->get();
    expect($paymentTxns)->toHaveCount(2);
    foreach ($paymentTxns as $txn) {
        $credit = LedgerEntry::where('ledger_transaction_id', $txn->id)->where('type', 'credit')->with('account')->first();
        expect($credit->account->name)->toBe('Accounts Receivable');
    }
});

it('marks a sold animal and shrinks a group when selling heads', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Goats',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $animal = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'name' => 'Billy',
        'gender' => 'male',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $group = AnimalGroup::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'name' => 'Layer flock',
        'initial_count' => 30,
        'current_count' => 30,
        'acquired_date' => '2026-01-01',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-11',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'animal', 'product' => 'goat', 'quantity' => 1, 'unit' => 'head', 'unit_price' => 8000, 'sellable_type' => 'animal', 'sellable_uuid' => $animal->uuid],
            ['category' => 'animal', 'product' => 'chicken', 'quantity' => 5, 'unit' => 'head', 'unit_price' => 700, 'sellable_type' => 'animal_group', 'sellable_uuid' => $group->uuid],
        ],
    ])->assertCreated()->assertJsonPath('data.amount_total', 11500);

    expect($animal->fresh()->status)->toBe('sold')
        ->and($group->fresh()->current_count)->toBe(25);
});

it('voids a sale, reversing the ledger and restoring the herd', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Cattle',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);

    $animal = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'name' => 'Bessie',
        'gender' => 'female',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-11',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'animal', 'product' => 'cow', 'quantity' => 1, 'unit' => 'head', 'unit_price' => 45000, 'sellable_type' => 'animal', 'sellable_uuid' => $animal->uuid],
        ],
    ])->assertCreated();

    $sale = Sale::first();
    expect($animal->fresh()->status)->toBe('sold');

    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI."/{$sale->uuid}/void")
        ->assertOk()
        ->assertJsonPath('data.status', 'void');

    expect($animal->fresh()->status)->toBe('active');

    // Reversal posted: sale now carries two transactions whose entries cancel out.
    $txns = $sale->ledgerTransactions()->get();
    expect($txns)->toHaveCount(2);
    $net = LedgerEntry::whereIn('ledger_transaction_id', $txns->pluck('id'))
        ->get()
        ->sum(fn ($e) => $e->type === 'debit' ? (float) $e->amount : -(float) $e->amount);
    expect($net)->toBe(0.0);
});

it('hides other farmers sales and rejects foreign farms', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $stranger = User::factory()->create();
    $strangerFarmer = Farmer::create([
        'uuid' => (string) Str::orderedUuid(),
        'display_name' => 'Other Farmer',
        'type' => 'individual',
        'status' => 1,
    ]);
    FarmerUser::create([
        'farmer_id' => $strangerFarmer->id,
        'user_id' => $stranger->id,
        'role' => 'owner',
        'status' => 1,
    ]);

    // A stranger cannot sell from someone else's farm.
    $this->actingAs($stranger, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-10',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'other', 'product' => 'manure', 'quantity' => 1, 'unit_price' => 500],
        ],
    ])->assertNotFound();

    // Nor see the owner's sales.
    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-10',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'other', 'product' => 'manure', 'quantity' => 1, 'unit_price' => 500],
        ],
    ])->assertCreated();

    $this->actingAs($stranger, 'sanctum')->getJson(SALES_BASE_URI.'/list')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('summarises sales by period, category and product', function () {
    [$user, $farmer, $farm] = salesTestContext();

    foreach ([
        ['date' => '2026-07-01', 'payment_method' => 'cash', 'category' => 'animal_product', 'product' => 'milk', 'quantity' => 20, 'unit_price' => 60],
        ['date' => '2026-07-05', 'payment_method' => 'credit', 'category' => 'bee_product', 'product' => 'honey', 'quantity' => 5, 'unit_price' => 900],
        ['date' => '2026-06-01', 'payment_method' => 'cash', 'category' => 'crop', 'product' => 'maize', 'quantity' => 2, 'unit_price' => 3000],
    ] as $row) {
        $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
            'farm_uuid' => $farm->uuid,
            'date' => $row['date'],
            'payment_method' => $row['payment_method'],
            'items' => [
                ['category' => $row['category'], 'product' => $row['product'], 'quantity' => $row['quantity'], 'unit_price' => $row['unit_price']],
            ],
        ])->assertCreated();
    }

    $this->actingAs($user, 'sanctum')
        ->getJson(SALES_BASE_URI.'/summary?from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertJsonPath('data.sales_count', 2)
        ->assertJsonPath('data.total_amount', 5700)
        ->assertJsonPath('data.owed_amount', 4500);
});

it('reports produced vs sold quantities per product', function () {
    [$user, $farmer, $farm] = salesTestContext();

    $type = AnimalType::create([
        'uuid' => (string) Str::orderedUuid(),
        'name' => 'Dairy Cows',
        'category' => 'livestock',
        'tracking_mode' => 'both',
    ]);
    $animal = Animal::create([
        'uuid' => (string) Str::orderedUuid(),
        'farm_id' => $farm->id,
        'farmer_id' => $farmer->id,
        'animal_type_id' => $type->id,
        'name' => 'Zawadi',
        'gender' => 'female',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    // Collected 25 litres, sold 20.
    Production::create([
        'uuid' => (string) Str::orderedUuid(),
        'productionable_type' => Animal::class,
        'productionable_id' => $animal->id,
        'name' => 'Milk',
        'date' => '2026-07-18',
        'quantity' => 25,
        'unit' => 'litres',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum')->postJson(SALES_BASE_URI, [
        'farm_uuid' => $farm->uuid,
        'date' => '2026-07-18',
        'payment_method' => 'cash',
        'items' => [
            ['category' => 'animal_product', 'product' => 'milk', 'quantity' => 20, 'unit' => 'litres', 'unit_price' => 60],
        ],
    ])->assertCreated();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/farms/farm/productions/summary?from=2026-07-01&to=2026-07-31')
        ->assertOk();

    $milk = collect($response->json('data'))->firstWhere('product', 'milk');
    expect($milk)->not->toBeNull()
        ->and((float) $milk['produced'])->toBe(25.0)
        ->and((float) $milk['sold'])->toBe(20.0)
        ->and((float) $milk['unsold'])->toBe(5.0);
});
