<?php

namespace App\Services\Ledger\Reports;

use Illuminate\Support\Collection;

/**
 * "Did the farm make money between these two dates?" — income less expenses
 * for the period, each account under its parent heading from the chart of
 * accounts.
 */
class ProfitAndLossService
{
    public function __construct(
        protected AccountBalanceQuery $balances,
        protected AccountSectionBuilder $sections,
    ) {}

    /**
     * @param  Collection<int, int>|array<int, int>  $farmIds
     * @return array<string, mixed>
     */
    public function generate(Collection|array $farmIds, string $from, string $to): array
    {
        $totals = $this->balances->totals($farmIds, $from, $to);
        [$accounts, $names] = $this->sections->chart();

        $income = $this->sections->build($accounts->where('type', 'revenue'), $totals, $names);
        $expenses = $this->sections->build($accounts->where('type', 'expense'), $totals, $names);

        return [
            'period' => ['date_from' => $from, 'date_to' => $to],
            'income' => $income,
            'expenses' => $expenses,
            'net_profit' => round($income['total'] - $expenses['total'], 2),
        ];
    }
}
