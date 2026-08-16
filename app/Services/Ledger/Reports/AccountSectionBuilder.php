<?php

namespace App\Services\Ledger\Reports;

use App\Models\Core\LedgerAccount;
use Illuminate\Support\Collection;

/**
 * Turns raw per-account totals into one readable half of a statement: posted
 * accounts sitting under their parent heading from the chart of accounts, so
 * the farmer reads "Expenses → Labour 3,000" rather than a flat list.
 *
 * Accounts with nothing posted are dropped — an empty row tells the farmer
 * nothing, and the full chart would bury the numbers that matter.
 */
class AccountSectionBuilder
{
    public function __construct(protected AccountBalanceQuery $balances) {}

    /**
     * @param  Collection<int, LedgerAccount>  $accounts  Accounts of a single type.
     * @param  Collection<int, object>  $totals  Debit/credit totals keyed by account id.
     * @param  Collection<int, string>  $names  Account names keyed by id, for parent headings.
     * @return array{groups: array<int, array<string, mixed>>, total: float}
     */
    public function build(Collection $accounts, Collection $totals, Collection $names): array
    {
        $rows = $accounts
            ->map(fn (LedgerAccount $account) => [
                'group' => $names[$account->parent_id] ?? $account->name,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'description' => $account->description,
                'amount' => $this->balances->balanceFor($account->type, $totals->get($account->id)),
            ])
            ->filter(fn (array $row) => abs($row['amount']) > 0.001)
            ->values();

        // Biggest first: what mattered should be readable without scrolling.
        $groups = $rows
            ->groupBy('group')
            ->map(fn (Collection $rows, string $group) => [
                'name' => $group,
                'total' => round($rows->sum('amount'), 2),
                'accounts' => $rows
                    ->map(fn (array $row) => collect($row)->except('group')->all())
                    ->sortBy([['amount', 'desc'], ['name', 'asc']])
                    ->values()
                    ->all(),
            ])
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'total' => round($rows->sum('amount'), 2),
        ];
    }

    /**
     * Every account in the chart, keyed for section building. Soft-deleted
     * accounts are included: archiving a category must not make the money
     * posted to it disappear from a statement (and unbalance the sheet).
     *
     * @return array{0: Collection<int, LedgerAccount>, 1: Collection<int, string>}
     */
    public function chart(): array
    {
        $accounts = LedgerAccount::withTrashed()->get();

        return [$accounts, $accounts->pluck('name', 'id')];
    }
}
