<?php

use App\Models\Core\LedgerEntry;
use App\Models\Core\LedgerTransaction;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const POSTING_TRANSACTIONS_URI = '/api/v1/farms/farm/transactions';

/**
 * The debit/credit pair a transaction produced, as ['Account name' => 'debit'].
 *
 * @return array<string, string>
 */
function postedSides(LedgerTransaction $transaction): array
{
    return LedgerEntry::query()
        ->where('ledger_transaction_id', $transaction->id)
        ->with('account')
        ->get()
        ->mapWithKeys(fn (LedgerEntry $entry) => [$entry->account->name => $entry->type])
        ->all();
}

it('records the owner taking money out of the farm', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    // Money in first, so there is something to draw against.
    postLedgerReportTransaction($owner, $farm, 'equity', "Owner's Capital", 50000, '2026-01-05');
    $drawing = postLedgerReportTransaction($owner, $farm, 'equity', 'Drawings', 8000, '2026-02-10', 'cash', 'decrease');

    // A drawing takes value out of the owner's stake and cash out of the till.
    expect(postedSides($drawing))->toBe(['Drawings' => 'debit', 'Cash' => 'credit']);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/farms/farm/reports/balance-sheet')
        ->assertOk()
        ->assertJsonPath('data.assets.total', 42000)
        ->assertJsonPath('data.equity.total', 42000)
        ->assertJsonPath('data.is_balanced', true);
});

it('records a loan being repaid', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    postLedgerReportTransaction($owner, $farm, 'liability', 'Loans', 20000, '2026-01-20', 'bank');
    $repayment = postLedgerReportTransaction($owner, $farm, 'liability', 'Loans', 5000, '2026-03-01', 'bank', 'decrease');

    expect(postedSides($repayment))->toBe(['Loans' => 'debit', 'Bank' => 'credit']);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/farms/farm/reports/balance-sheet')
        ->assertOk()
        ->assertJsonPath('data.assets.total', 15000)
        ->assertJsonPath('data.liabilities.total', 15000)
        ->assertJsonPath('data.is_balanced', true);
});

it('records equipment being sold', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    postLedgerReportTransaction($owner, $farm, 'equity', "Owner's Capital", 50000, '2026-01-05');
    postLedgerReportTransaction($owner, $farm, 'asset', 'Equipment & Tools', 30000, '2026-02-01');
    $sale = postLedgerReportTransaction($owner, $farm, 'asset', 'Equipment & Tools', 12000, '2026-04-01', 'cash', 'decrease');

    // Selling is the mirror of buying: the equipment goes, the cash arrives.
    expect(postedSides($sale))->toBe(['Equipment & Tools' => 'credit', 'Cash' => 'debit']);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/farms/farm/reports/balance-sheet')
        ->assertOk()
        ->assertJsonPath('data.assets.total', 50000)
        ->assertJsonPath('data.is_balanced', true);
});

it('still posts everything the old way when no effect is given', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    $sale = postLedgerReportTransaction($owner, $farm, 'revenue', 'Crop Sales', 10000, '2026-03-10');
    $wages = postLedgerReportTransaction($owner, $farm, 'expense', 'Labour', 3000, '2026-03-11');
    $capital = postLedgerReportTransaction($owner, $farm, 'equity', "Owner's Capital", 5000, '2026-03-12');
    $loan = postLedgerReportTransaction($owner, $farm, 'liability', 'Loans', 7000, '2026-03-13', 'bank');
    $purchase = postLedgerReportTransaction($owner, $farm, 'asset', 'Equipment & Tools', 4000, '2026-03-14');

    expect(postedSides($sale))->toBe(['Crop Sales' => 'credit', 'Cash' => 'debit'])
        ->and(postedSides($wages))->toBe(['Labour' => 'debit', 'Cash' => 'credit'])
        ->and(postedSides($capital))->toBe(["Owner's Capital" => 'credit', 'Cash' => 'debit'])
        ->and(postedSides($loan))->toBe(['Loans' => 'credit', 'Bank' => 'debit'])
        ->and(postedSides($purchase))->toBe(['Equipment & Tools' => 'debit', 'Cash' => 'credit']);
});

it('accepts a decrease through the transactions endpoint', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    $this->actingAs($owner, 'sanctum')->postJson(POSTING_TRANSACTIONS_URI, [
        'date' => '2026-02-10',
        'payment_method' => 'cash',
        'type' => 'equity',
        'effect' => 'decrease',
        'transaction_for' => 'farm',
        'transaction_uuid' => $farm->uuid,
        'entries' => [
            ['ledger_account_id' => ledgerReportAccount('Drawings')->id, 'amount' => 2000],
        ],
    ])->assertCreated();

    expect(postedSides(LedgerTransaction::query()->firstOrFail()))
        ->toBe(['Drawings' => 'debit', 'Cash' => 'credit']);
});

it('refuses to record money in or out backwards', function () {
    $this->seed(LedgerAccountsSeeder::class);

    ['owner' => $owner, 'farmA' => $farm] = ledgerReportChain();

    $this->actingAs($owner, 'sanctum')->postJson(POSTING_TRANSACTIONS_URI, [
        'date' => '2026-02-10',
        'payment_method' => 'cash',
        'type' => 'expense',
        'effect' => 'decrease',
        'transaction_for' => 'farm',
        'transaction_uuid' => $farm->uuid,
        'entries' => [
            ['ledger_account_id' => ledgerReportAccount('Labour')->id, 'amount' => 2000],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('effect');

    $this->assertDatabaseCount('ledger_transactions', 0);
});
