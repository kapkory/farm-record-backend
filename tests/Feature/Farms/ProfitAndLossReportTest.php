<?php

use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const PROFIT_AND_LOSS_URI = '/api/v1/farms/farm/reports/profit-and-loss';

it('reports income less expenses for the requested period', function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->travelTo(Carbon::parse('2026-04-20'));

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    // Inside the period.
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 10000, '2026-03-10');
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Milk, Eggs & Honey Sales', 4000, '2026-03-20', 'credit');
    postLedgerReportTransaction($owner, $farm, 'expense', 'Labour', 3000, '2026-04-05');
    postLedgerReportTransaction($owner, $farm, 'expense', 'Animal Feed', 1500, '2026-04-15', 'mobile_money');

    // A mistake and its correction — both land in the period, so they cancel.
    $voided = postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 5000, '2026-03-25');
    app(LedgerTransactionService::class)->reverse($owner, $voided);

    // Outside the period on both sides.
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 99999, '2026-01-10');
    postLedgerReportTransaction($owner, $farm, 'expense', 'Labour', 88888, '2026-06-01');

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI.'?date_from=2026-03-01&date_to=2026-05-31');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.period.date_from', '2026-03-01')
        ->assertJsonPath('data.period.date_to', '2026-05-31')
        ->assertJsonPath('data.income.total', 14000)
        ->assertJsonPath('data.expenses.total', 4500)
        ->assertJsonPath('data.net_profit', 9500)
        // Biggest first, under the chart-of-accounts parent heading.
        ->assertJsonPath('data.income.groups.0.name', 'Income')
        ->assertJsonPath('data.income.groups.0.accounts.0.name', 'Crop Sales')
        ->assertJsonPath('data.income.groups.0.accounts.0.amount', 10000)
        ->assertJsonPath('data.income.groups.0.accounts.1.name', 'Milk, Eggs & Honey Sales')
        ->assertJsonPath('data.income.groups.0.accounts.1.amount', 4000)
        ->assertJsonCount(2, 'data.income.groups.0.accounts')
        ->assertJsonPath('data.expenses.groups.0.name', 'Expenses')
        ->assertJsonPath('data.expenses.groups.0.accounts.0.name', 'Labour')
        ->assertJsonPath('data.expenses.groups.0.accounts.0.amount', 3000)
        ->assertJsonPath('data.expenses.groups.0.accounts.1.name', 'Animal Feed')
        ->assertJsonPath('data.expenses.groups.0.accounts.1.amount', 1500)
        ->assertJsonCount(2, 'data.expenses.groups.0.accounts');
});

it('defaults to the year to date', function () {
    $this->seed(LedgerAccountsSeeder::class);
    $this->travelTo(Carbon::parse('2026-04-20'));

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 7000, '2026-02-01');
    postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 6000, '2025-12-20');

    $this->actingAs($owner, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI)
        ->assertOk()
        ->assertJsonPath('data.period.date_from', '2026-01-01')
        ->assertJsonPath('data.period.date_to', '2026-04-20')
        ->assertJsonPath('data.income.total', 7000);
});

it('narrows the statement to a single farm', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farmA, 'farmB' => $farmB] = ledgerReportChain();

    postLedgerReportTransaction($owner, $farmA, 'revenue', 'Crop Sales', 8000, '2026-03-01');
    postLedgerReportTransaction($owner, $farmB, 'revenue', 'Crop Sales', 2000, '2026-03-01');

    $query = '?date_from=2026-01-01&date_to=2026-12-31';

    $this->actingAs($owner, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI.$query)
        ->assertOk()
        ->assertJsonPath('data.income.total', 10000);

    $this->actingAs($owner, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI.$query.'&farm_uuid='.$farmB->uuid)
        ->assertOk()
        ->assertJsonPath('data.income.total', 2000);
});

it('rejects a farm the account cannot reach', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner] = ledgerReportChain();
    ['farmA' => $otherFarm] = ledgerReportChain('Someone Else');

    $this->actingAs($owner, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI.'?farm_uuid='.$otherFarm->uuid)
        ->assertStatus(422)
        ->assertJsonPath('errors.farm_uuid.0', 'Choose a farm you have access to.');
});

it('never totals another farmer money', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $mine] = ledgerReportChain();
    ['owner' => $theirs, 'farmA' => $theirFarm] = ledgerReportChain('Neighbour');

    postLedgerReportTransaction($theirs, $theirFarm, 'revenue', 'Crop Sales', 50000, '2026-03-01');

    $this->actingAs($mine, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI.'?date_from=2026-01-01&date_to=2026-12-31')
        ->assertOk()
        ->assertJsonPath('data.income.total', 0)
        ->assertJsonPath('data.net_profit', 0)
        ->assertJsonCount(0, 'data.income.groups');
});

it('keeps the statement away from staff logins', function () {
    ['staff' => $staff] = ledgerReportChain();

    $this->actingAs($staff, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI)
        ->assertForbidden();
});

it('rejects an end date before the start date', function () {
    ['owner' => $owner] = ledgerReportChain();

    $this->actingAs($owner, 'sanctum')
        ->getJson(PROFIT_AND_LOSS_URI.'?date_from=2026-05-01&date_to=2026-04-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_to');
});
