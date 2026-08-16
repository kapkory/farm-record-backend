<?php

use App\Models\Core\Farm;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const CASH_FLOW_URI = '/api/v1/farms/farm/reports/cash-flow';

/**
 * A first season with money moving every way it can.
 *
 * Day-to-day        in 10,000 (crop sale)   out  3,000 (wages)     net   7,000
 * Big buys          in 12,000 (tools sold)  out 30,000 (tools)     net -18,000
 * Loans & own money in 70,000 (capital,     out 13,000 (loan       net  57,000
 *                              loan)                repaid, drawing)
 *                                                    Net movement       46,000
 *
 * The 4,000 credit sale is profit but not cash, and the 6,000 cash-to-bank
 * transfer is not a flow at all — neither may touch the net movement.
 */
function seedCashFlowSeason(User $owner, Farm $farm): void
{
    postLedgerReportTransaction($owner, $farm, 'equity', "Owner's Capital", 50000, '2026-01-05');
    postLedgerReportTransaction($owner, $farm, 'liability', 'Loans', 20000, '2026-01-20', 'bank');
    postLedgerReportTransaction($owner, $farm, 'asset', 'Equipment & Tools', 30000, '2026-02-01');
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 10000, '2026-03-10');
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Milk, Eggs & Honey Sales', 4000, '2026-03-20', 'credit');
    postLedgerReportTransaction($owner, $farm, 'expense', 'Labour', 3000, '2026-04-05');
    postLedgerReportTransaction($owner, $farm, 'liability', 'Loans', 5000, '2026-04-20', 'bank', 'decrease');
    postLedgerReportTransaction($owner, $farm, 'equity', 'Drawings', 8000, '2026-05-01', 'cash', 'decrease');
    postLedgerReportTransaction($owner, $farm, 'asset', 'Equipment & Tools', 12000, '2026-05-10', 'cash', 'decrease');

    app(LedgerTransactionService::class)->transfer(
        user: $owner,
        farmerId: $farm->farmer_id,
        farmId: $farm->id,
        transactionable: $farm,
        date: Carbon::parse('2026-05-15'),
        amount: 6000,
        fromAccount: ledgerReportAccount('Cash'),
        toAccount: ledgerReportAccount('Bank'),
        description: 'Banked the season takings',
        paymentMethod: 'bank',
    );
}

/** The section of a response keyed by its slug, e.g. 'operating'. */
function cashFlowSection(array $sections, string $key): array
{
    return collect($sections)->firstWhere('key', $key);
}

it('reports where the cash came from and where it went', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedCashFlowSeason($owner, $farm);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?date_from=2026-01-01&date_to=2026-06-30');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.opening_cash', 0)
        ->assertJsonPath('data.net_movement', 46000)
        ->assertJsonPath('data.closing_cash', 46000)
        ->assertJsonPath('data.difference', 0)
        ->assertJsonPath('data.reconciles', true);

    $sections = $response->json('data.sections');

    expect(cashFlowSection($sections, 'operating'))
        ->toMatchArray(['name' => 'Day-to-day farming', 'in' => 10000, 'out' => 3000, 'net' => 7000])
        ->and(cashFlowSection($sections, 'investing'))
        ->toMatchArray(['in' => 12000, 'out' => 30000, 'net' => -18000])
        ->and(cashFlowSection($sections, 'financing'))
        ->toMatchArray(['in' => 70000, 'out' => 13000, 'net' => 57000]);

    // Each section names what the money was for.
    expect(collect(cashFlowSection($sections, 'operating')['lines'])->pluck('net', 'name')->all())
        ->toBe(['Crop Sales' => 10000, 'Labour' => -3000]);
});

it('leaves credit sales and internal transfers out of the flow', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedCashFlowSeason($owner, $farm);

    $lines = collect($this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?date_from=2026-01-01&date_to=2026-06-30')
        ->json('data.sections'))
        ->flatMap(fn (array $section) => collect($section['lines'])->pluck('name'))
        ->all();

    // The credit sale never touched cash, and banking your own takings is not
    // money the farm gained or lost.
    expect($lines)->not->toContain('Milk, Eggs & Honey Sales')
        ->and($lines)->not->toContain('Cash')
        ->and($lines)->not->toContain('Bank');
});

it('counts a buyer settling a credit sale as day-to-day money in', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    postLedgerReportTransaction($owner, $farm, 'revenue', 'Milk, Eggs & Honey Sales', 4000, '2026-03-20', 'credit');

    app(LedgerTransactionService::class)->transfer(
        user: $owner,
        farmerId: $farm->farmer_id,
        farmId: $farm->id,
        transactionable: $farm,
        date: Carbon::parse('2026-04-02'),
        amount: 4000,
        fromAccount: ledgerReportAccount('Accounts Receivable'),
        toAccount: ledgerReportAccount('Cash'),
        description: 'Buyer paid up',
        paymentMethod: 'cash',
    );

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?date_from=2026-01-01&date_to=2026-06-30');

    $response->assertOk()
        ->assertJsonPath('data.net_movement', 4000)
        ->assertJsonPath('data.closing_cash', 4000)
        ->assertJsonPath('data.reconciles', true);

    expect(cashFlowSection($response->json('data.sections'), 'operating')['lines'])
        ->toBe([['name' => 'Accounts Receivable', 'in' => 4000, 'out' => 0, 'net' => 4000]]);
});

it('opens a mid-season window with the cash already in hand', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedCashFlowSeason($owner, $farm);

    // By 1 April the farm holds 30,000 cash and 20,000 in the bank; the rest of
    // the season then spends 4,000 net.
    $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?date_from=2026-04-01&date_to=2026-06-30')
        ->assertOk()
        ->assertJsonPath('data.opening_cash', 50000)
        ->assertJsonPath('data.net_movement', -4000)
        ->assertJsonPath('data.closing_cash', 46000)
        ->assertJsonPath('data.reconciles', true);
});

it('agrees with the cash on the balance sheet', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedCashFlowSeason($owner, $farm);

    $closing = $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?date_from=2026-01-01&date_to=2026-06-30')
        ->json('data.closing_cash');

    $assets = collect($this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/farms/farm/reports/balance-sheet?as_of=2026-06-30')
        ->json('data.assets.groups'))
        ->flatMap(fn (array $group) => $group['accounts'])
        ->pluck('amount', 'name');

    expect($assets['Cash'] + $assets['Bank'])->toBe($closing);
});

it('defaults to the year to date for cash flow', function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->travelTo(Carbon::parse('2026-03-31'));

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedCashFlowSeason($owner, $farm);

    $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI)
        ->assertOk()
        ->assertJsonPath('data.period.date_from', '2026-01-01')
        ->assertJsonPath('data.period.date_to', '2026-03-31')
        // Capital, loan, tools bought and the crop sale — nothing from April on.
        ->assertJsonPath('data.net_movement', 50000)
        ->assertJsonPath('data.reconciles', true);
});

it('narrows the flow to a single farm', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farmA, 'farmB' => $farmB] = ledgerReportChain();
    seedCashFlowSeason($owner, $farmA);
    postLedgerReportTransaction($owner, $farmB, 'revenue', 'Crop Sales', 3000, '2026-03-01');

    $query = '?date_from=2026-01-01&date_to=2026-06-30';

    $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.$query)
        ->assertOk()
        ->assertJsonPath('data.net_movement', 49000);

    $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.$query.'&farm_uuid='.$farmB->uuid)
        ->assertOk()
        ->assertJsonPath('data.net_movement', 3000)
        ->assertJsonPath('data.closing_cash', 3000);
});

it('rejects a farm the account cannot reach for the flow', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner] = ledgerReportChain();
    ['farmA' => $otherFarm] = ledgerReportChain('Someone Else');

    $this->actingAs($owner, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?farm_uuid='.$otherFarm->uuid)
        ->assertStatus(422)
        ->assertJsonPath('errors.farm_uuid.0', 'Choose a farm you have access to.');
});

it('never shows another farmer cash', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $mine] = ledgerReportChain();
    ['owner' => $theirs, 'farmA' => $theirFarm] = ledgerReportChain('Neighbour');
    seedCashFlowSeason($theirs, $theirFarm);

    $this->actingAs($mine, 'sanctum')
        ->getJson(CASH_FLOW_URI.'?date_from=2026-01-01&date_to=2026-06-30')
        ->assertOk()
        ->assertJsonPath('data.opening_cash', 0)
        ->assertJsonPath('data.net_movement', 0)
        ->assertJsonPath('data.closing_cash', 0);
});

it('keeps the flow away from staff logins', function () {
    ['staff' => $staff] = ledgerReportChain();

    $this->actingAs($staff, 'sanctum')
        ->getJson(CASH_FLOW_URI)
        ->assertForbidden();
});
