<?php

namespace App\Services\Ledger\Reports;

use App\Models\Core\LedgerAccount;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

/**
 * "The books say I made money — so why is the tin empty?" Profit and cash are
 * not the same thing: a credit sale is profit with no money, a tractor is money
 * with no cost, a loan is money that is not income.
 *
 * Built the direct way — every movement in and out of a cash account, sorted by
 * what it was for — rather than the indirect way, because there is no accruals
 * engine to reconcile against and cash moves are what a farmer recognises.
 */
class CashFlowService
{
    /** Accounts that hold money the farmer can actually spend today. */
    private const CASH_SLUGS = ['cash', 'mobile-money', 'bank'];

    private const SECTIONS = [
        'operating' => 'Day-to-day farming',
        'investing' => 'Equipment, animals & other big buys',
        'financing' => 'Loans & your own money',
    ];

    public function __construct(
        protected DatabaseManager $db,
        protected AccountBalanceQuery $balances,
    ) {}

    /**
     * @param  Collection<int, int>|array<int, int>  $farmIds
     * @return array<string, mixed>
     */
    public function generate(Collection|array $farmIds, string $from, string $to): array
    {
        $openingCash = $this->cashBalance($farmIds, Carbon::parse($from)->subDay()->toDateString());
        $closingCash = $this->cashBalance($farmIds, $to);

        $lines = $this->groupMovements($this->cashMovements($farmIds, $from, $to));

        $sections = [];
        $netMovement = 0.0;

        foreach (self::SECTIONS as $key => $name) {
            $rows = collect($lines[$key] ?? [])
                ->map(fn (array $row) => [
                    'name' => $row['name'],
                    'in' => round($row['in'], 2),
                    'out' => round($row['out'], 2),
                    'net' => round($row['in'] - $row['out'], 2),
                ])
                ->sortBy([['net', 'desc'], ['name', 'asc']])
                ->values();

            $in = round($rows->sum('in'), 2);
            $out = round($rows->sum('out'), 2);

            $sections[] = [
                'key' => $key,
                'name' => $name,
                'in' => $in,
                'out' => $out,
                'net' => round($in - $out, 2),
                'lines' => $rows->all(),
            ];

            $netMovement += $in - $out;
        }

        $netMovement = round($netMovement, 2);
        $difference = round($openingCash + $netMovement - $closingCash, 2);

        return [
            'period' => ['date_from' => $from, 'date_to' => $to],
            'opening_cash' => $openingCash,
            'sections' => $sections,
            'net_movement' => $netMovement,
            'closing_cash' => $closingCash,
            // Opening plus movement must land exactly on closing. Surfacing the
            // gap turns a classification bug into a warning the screen can show
            // rather than a total the farmer quietly trusts.
            'difference' => $difference,
            'reconciles' => abs($difference) < 0.01,
        ];
    }

    /**
     * Every movement in or out of a cash account, labelled with the account on
     * the other side of the transaction — which is what says whether the money
     * was a sale, a loan, or a tractor.
     *
     * @param  Collection<int, int>|array<int, int>  $farmIds
     * @return Collection<int, object>
     */
    private function cashMovements(Collection|array $farmIds, string $from, string $to): Collection
    {
        $farmIds = collect($farmIds)->all();

        if ($farmIds === []) {
            return collect();
        }

        return collect($this->db->table('ledger_entries as cash')
            ->join('ledger_transactions as lt', 'lt.id', '=', 'cash.ledger_transaction_id')
            ->join('ledger_accounts as cash_account', 'cash_account.id', '=', 'cash.ledger_account_id')
            ->join('ledger_entries as other', function ($join) {
                $join->on('other.ledger_transaction_id', '=', 'cash.ledger_transaction_id')
                    ->on('other.id', '!=', 'cash.id');
            })
            ->join('ledger_accounts as other_account', 'other_account.id', '=', 'other.ledger_account_id')
            ->whereIn('lt.farm_id', $farmIds)
            ->whereNull('lt.deleted_at')
            ->whereIn('cash_account.slug', self::CASH_SLUGS)
            ->whereDate('lt.date', '>=', $from)
            ->whereDate('lt.date', '<=', $to)
            ->groupBy('other_account.slug', 'other_account.name', 'cash.type')
            ->selectRaw('other_account.slug as slug, other_account.name as name, cash.type as side')
            ->selectRaw('COALESCE(SUM(cash.amount), 0) as total')
            ->get());
    }

    /**
     * @param  Collection<int, object>  $movements
     * @return array<string, array<string, array{name: string, in: float, out: float}>>
     */
    private function groupMovements(Collection $movements): array
    {
        $lines = [];

        foreach ($movements as $movement) {
            $section = $this->sectionFor($movement->slug);

            if ($section === null) {
                continue;
            }

            $lines[$section][$movement->name] ??= ['name' => $movement->name, 'in' => 0.0, 'out' => 0.0];

            // Cash debited is money arriving; cash credited is money leaving.
            $direction = $movement->side === 'debit' ? 'in' : 'out';
            $lines[$section][$movement->name][$direction] += (float) $movement->total;
        }

        return $lines;
    }

    /**
     * Which part of the statement a movement belongs to, read off the account
     * on the other side. Driven by the seeded chart's slugs: an account a
     * farmer added themselves falls into day-to-day farming, which is right for
     * nearly all of them. The day that stops being true is the day this earns a
     * real `cash_flow_section` column — not before.
     */
    private function sectionFor(string $slug): ?string
    {
        if (in_array($slug, self::CASH_SLUGS, true)) {
            // Cash to bank to M-Pesa is moving money between your own pockets,
            // not money the farm gained or lost.
            return null;
        }

        return match ($slug) {
            'equipment-tools', 'livestock' => 'investing',
            'loans', 'owners-capital', 'drawings' => 'financing',
            default => 'operating',
        };
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $farmIds
     */
    private function cashBalance(Collection|array $farmIds, string $asOf): float
    {
        $totals = $this->balances->totals($farmIds, null, $asOf);

        $cashAccounts = LedgerAccount::withTrashed()->whereIn('slug', self::CASH_SLUGS)->get();

        return round((float) $cashAccounts->sum(
            fn (LedgerAccount $account) => $this->balances->balanceFor($account->type, $totals->get($account->id))
        ), 2);
    }
}
