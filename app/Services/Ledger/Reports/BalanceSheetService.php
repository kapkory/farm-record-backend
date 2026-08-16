<?php

namespace App\Services\Ledger\Reports;

use App\Models\Core\LedgerAccount;
use Illuminate\Support\Collection;

/**
 * "What is the farm worth today?" — everything owned, everything owed, and the
 * owner's stake, as at a single date. Balances are cumulative from the first
 * transaction, not for a period, so this reads the ledger with no lower bound.
 */
class BalanceSheetService
{
    public function __construct(
        protected AccountBalanceQuery $balances,
        protected AccountSectionBuilder $sections,
    ) {}

    /**
     * @param  Collection<int, int>|array<int, int>  $farmIds
     * @return array<string, mixed>
     */
    public function generate(Collection|array $farmIds, string $asOf): array
    {
        $totals = $this->balances->totals($farmIds, null, $asOf);
        [$accounts, $names] = $this->sections->chart();

        $assets = $this->sections->build($accounts->where('type', 'asset'), $totals, $names);
        $liabilities = $this->sections->build($accounts->where('type', 'liability'), $totals, $names);
        $equity = $this->sections->build($accounts->where('type', 'equity'), $totals, $names);

        // Nothing closes the books at year end, so profit earned so far is not
        // yet sitting in an equity account. Roll it into the owner's stake as
        // its own line or the sheet cannot balance.
        $currentEarnings = round(
            $this->sumOf($accounts->where('type', 'revenue'), $totals)
            - $this->sumOf($accounts->where('type', 'expense'), $totals),
            2
        );

        $equity['current_earnings'] = $currentEarnings;
        $equity['total'] = round($equity['total'] + $currentEarnings, 2);

        $liabilitiesAndEquity = round($liabilities['total'] + $equity['total'], 2);
        $difference = round($assets['total'] - $liabilitiesAndEquity, 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_liabilities_and_equity' => $liabilitiesAndEquity,
            // Double entry makes this zero; surfacing it turns a posting bug
            // into something the screen can warn about instead of a wrong
            // number the farmer trusts.
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
        ];
    }

    /**
     * @param  Collection<int, LedgerAccount>  $accounts
     * @param  Collection<int, object>  $totals
     */
    private function sumOf(Collection $accounts, Collection $totals): float
    {
        return (float) $accounts->sum(
            fn (LedgerAccount $account) => $this->balances->balanceFor($account->type, $totals->get($account->id))
        );
    }
}
