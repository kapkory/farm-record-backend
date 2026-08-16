<?php

use App\Models\Core\Farm;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const BALANCE_SHEET_URI = '/api/v1/farms/farm/reports/balance-sheet';

/**
 * A full first season on one farm: money put in, a loan taken, equipment
 * bought, produce sold for cash and on credit, and wages paid.
 *
 * Cash     50,000 in − 30,000 equipment + 10,000 sale − 3,000 wages = 27,000
 * Bank     20,000 loan
 * Equipment                                                          30,000
 * Owed by buyers                                                      4,000
 *                                                            Assets  81,000
 *
 * Loans                                                              20,000
 * Owner's Capital 50,000 + earnings (10,000 + 4,000 − 3,000)         61,000
 *                                            Liabilities + equity    81,000
 */
function seedBalanceSheetSeason(User $owner, Farm $farm): void
{
    postLedgerReportTransaction($owner, $farm, 'equity', "Owner's Capital", 50000, '2026-01-05');
    postLedgerReportTransaction($owner, $farm, 'liability', 'Loans', 20000, '2026-01-20', 'bank');
    postLedgerReportTransaction($owner, $farm, 'asset', 'Equipment & Tools', 30000, '2026-02-01');
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 10000, '2026-03-10');
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Milk, Eggs & Honey Sales', 4000, '2026-03-20', 'credit');
    postLedgerReportTransaction($owner, $farm, 'expense', 'Labour', 3000, '2026-04-05');
}

it('reports what the farm owns owes and is worth', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedBalanceSheetSeason($owner, $farm);

    $response = $this->actingAs($owner, 'sanctum')->getJson(BALANCE_SHEET_URI.'?as_of=2026-04-30');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.as_of', '2026-04-30')
        ->assertJsonPath('data.assets.total', 81000)
        ->assertJsonPath('data.liabilities.total', 20000)
        ->assertJsonPath('data.equity.current_earnings', 11000)
        ->assertJsonPath('data.equity.total', 61000)
        ->assertJsonPath('data.total_liabilities_and_equity', 81000)
        ->assertJsonPath('data.difference', 0)
        ->assertJsonPath('data.is_balanced', true);

    // Assets sit under their chart heading, biggest first.
    $assets = collect($response->json('data.assets.groups'))->firstWhere('name', 'Assets');

    expect(collect($assets['accounts'])->pluck('amount', 'name')->all())->toBe([
        'Equipment & Tools' => 30000,
        'Cash' => 27000,
        'Bank' => 20000,
        'Accounts Receivable' => 4000,
    ]);
});

it('counts everything up to the date and nothing after it', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedBalanceSheetSeason($owner, $farm);

    // As at the end of January only the capital and the loan have happened.
    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI.'?as_of=2026-01-31')
        ->assertOk()
        ->assertJsonPath('data.assets.total', 70000)
        ->assertJsonPath('data.liabilities.total', 20000)
        ->assertJsonPath('data.equity.current_earnings', 0)
        ->assertJsonPath('data.equity.total', 50000)
        ->assertJsonPath('data.is_balanced', true);
});

it('defaults to today', function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->travelTo(Carbon::parse('2026-03-15'));

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedBalanceSheetSeason($owner, $farm);

    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI)
        ->assertOk()
        ->assertJsonPath('data.as_of', '2026-03-15')
        // The 20 March credit sale and April wages have not happened yet.
        ->assertJsonPath('data.equity.current_earnings', 10000)
        ->assertJsonPath('data.is_balanced', true);
});

it('still balances once a mistake has been reversed', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedBalanceSheetSeason($owner, $farm);

    $voided = postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 5000, '2026-04-10');
    app(LedgerTransactionService::class)->reverse($owner, $voided);

    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI)
        ->assertOk()
        ->assertJsonPath('data.assets.total', 81000)
        ->assertJsonPath('data.equity.current_earnings', 11000)
        ->assertJsonPath('data.is_balanced', true);
});

it('keeps money posted to an archived account on the sheet', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();
    seedBalanceSheetSeason($owner, $farm);

    // Archiving a category must not make its balance vanish, or the sheet
    // would silently stop balancing.
    ledgerReportAccount('Equipment & Tools')->delete();

    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI)
        ->assertOk()
        ->assertJsonPath('data.assets.total', 81000)
        ->assertJsonPath('data.is_balanced', true);
});

it('narrows the sheet to a single farm', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farmA, 'farmB' => $farmB] = ledgerReportChain();
    seedBalanceSheetSeason($owner, $farmA);
    postLedgerReportTransaction($owner, $farmB, 'equity', "Owner's Capital", 5000, '2026-01-05');

    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI)
        ->assertOk()
        ->assertJsonPath('data.assets.total', 86000);

    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI.'?farm_uuid='.$farmB->uuid)
        ->assertOk()
        ->assertJsonPath('data.assets.total', 5000)
        ->assertJsonPath('data.equity.total', 5000)
        ->assertJsonPath('data.is_balanced', true);
});

it('rejects a farm the account cannot reach for the sheet', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner] = ledgerReportChain();
    ['farmA' => $otherFarm] = ledgerReportChain('Someone Else');

    $this->actingAs($owner, 'sanctum')
        ->getJson(BALANCE_SHEET_URI.'?farm_uuid='.$otherFarm->uuid)
        ->assertStatus(422)
        ->assertJsonPath('errors.farm_uuid.0', 'Choose a farm you have access to.');
});

it('never shows another farmer worth', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $mine] = ledgerReportChain();
    ['owner' => $theirs, 'farmA' => $theirFarm] = ledgerReportChain('Neighbour');
    seedBalanceSheetSeason($theirs, $theirFarm);

    $this->actingAs($mine, 'sanctum')
        ->getJson(BALANCE_SHEET_URI)
        ->assertOk()
        ->assertJsonPath('data.assets.total', 0)
        ->assertJsonPath('data.equity.total', 0)
        ->assertJsonCount(0, 'data.assets.groups');
});

it('keeps the sheet away from staff logins', function () {
    ['staff' => $staff] = ledgerReportChain();

    $this->actingAs($staff, 'sanctum')
        ->getJson(BALANCE_SHEET_URI)
        ->assertForbidden();
});
