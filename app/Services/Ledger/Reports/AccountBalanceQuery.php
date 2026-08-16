<?php

namespace App\Services\Ledger\Reports;

use App\Services\Ledger\Support\LedgerPostingRuleResolver;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * The single place that knows how to total a ledger by account. Every
 * statement (profit and loss, balance sheet, cash flow) reads its numbers
 * through here, so the scoping and sign rules can only be wrong in one place.
 *
 * Scoping goes through `ledger_transactions.farm_id`, never
 * `ledger_accounts.farmer_id`: system accounts are shared (farmer_id null), so
 * the accounts table says nothing about whose money a balance is.
 */
class AccountBalanceQuery
{
    public function __construct(
        protected DatabaseManager $db,
        protected LedgerPostingRuleResolver $rules,
    ) {}

    /**
     * Debit and credit totals per account, keyed by ledger account id. A null
     * date bound means "no bound on that side" — the balance sheet passes a
     * `to` only, the profit and loss passes both.
     *
     * @param  Collection<int, int>|array<int, int>  $farmIds
     * @return Collection<int, object{total_debit: float, total_credit: float}>
     */
    public function totals(Collection|array $farmIds, ?string $from, ?string $to): Collection
    {
        $farmIds = collect($farmIds)->all();

        if ($farmIds === []) {
            return collect();
        }

        return $this->db->table('ledger_entries as le')
            ->join('ledger_transactions as lt', 'lt.id', '=', 'le.ledger_transaction_id')
            ->whereIn('lt.farm_id', $farmIds)
            // Corrections are posted as reversals, but a transaction that was
            // soft deleted before that rule existed must not still count.
            ->whereNull('lt.deleted_at')
            ->when($from !== null, fn ($query) => $query->whereDate('lt.date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('lt.date', '<=', $to))
            ->groupBy('le.ledger_account_id')
            ->selectRaw('le.ledger_account_id as account_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN le.type = 'debit' THEN le.amount ELSE 0 END), 0) as total_debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN le.type = 'credit' THEN le.amount ELSE 0 END), 0) as total_credit")
            ->get()
            ->keyBy('account_id')
            ->map(fn ($row) => (object) [
                'total_debit' => (float) $row->total_debit,
                'total_credit' => (float) $row->total_credit,
            ]);
    }

    /**
     * The balance in the account's own direction: positive means more of what
     * the account is for (more cash, more income, more debt owed).
     */
    public function balanceFor(string $accountType, ?object $totals): float
    {
        if ($totals === null) {
            return 0.0;
        }

        return $this->rules->normalBalanceFor($accountType) === 'debit'
            ? round($totals->total_debit - $totals->total_credit, 2)
            : round($totals->total_credit - $totals->total_debit, 2);
    }
}
