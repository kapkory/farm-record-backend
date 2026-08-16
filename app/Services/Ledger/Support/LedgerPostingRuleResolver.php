<?php

namespace App\Services\Ledger\Support;

use InvalidArgumentException;

class LedgerPostingRuleResolver
{
    public function entryTypeFor(string $accountType, string $effect): string
    {
        return match ($accountType) {
            'asset', 'expense' => $effect === 'increase' ? 'debit' : 'credit',
            'liability', 'equity', 'revenue' => $effect === 'increase' ? 'credit' : 'debit',
            default => throw new InvalidArgumentException("Unsupported ledger account type [{$accountType}]."),
        };
    }

    /**
     * The side that increases this account type. Reports use it to sign a
     * balance so "positive" always means more of what the account is for.
     */
    public function normalBalanceFor(string $accountType): string
    {
        return $this->entryTypeFor($accountType, 'increase');
    }

    /**
     * Which way the account being recorded against moves.
     *
     * Income and expenses only ever go one way — a mistake there is corrected
     * with a reversal, never a backwards posting. The balance-sheet types move
     * both ways: equipment is bought and sold, loans are taken and repaid, and
     * the owner both puts money into the farm and draws money out of it.
     */
    public function effectForPrimaryAccount(string $transactionType, string $effect = 'increase'): string
    {
        if (! in_array($effect, ['increase', 'decrease'], true)) {
            throw new InvalidArgumentException("Unsupported effect [{$effect}].");
        }

        return match ($transactionType) {
            'expense', 'income', 'revenue' => $effect === 'increase'
                ? 'increase'
                : throw new InvalidArgumentException("A [{$transactionType}] transaction cannot decrease — reverse it instead."),
            'asset', 'liability', 'equity' => $effect,
            default => throw new InvalidArgumentException("Unsupported transaction type [{$transactionType}]."),
        };
    }

    public function contraAccountTypeFor(string $paymentMethod, string $transactionType): string
    {
        return match ($paymentMethod) {
            'cash', 'mobile_money', 'bank' => 'asset',
            'credit' => $transactionType === 'income' || $transactionType === 'revenue' ? 'asset' : 'liability',
            default => throw new InvalidArgumentException("Unsupported payment method [{$paymentMethod}]."),
        };
    }

    /**
     * How the money side moves, given the way the primary account moved.
     * Spending and buying push cash the opposite way to the account (pay for
     * equipment, cash falls; sell it, cash rises). Income, borrowing and owner
     * money push it the same way (repay a loan or take a drawing and both the
     * account and the cash fall).
     */
    public function contraEffect(string $transactionType, string $primaryEffect = 'increase'): string
    {
        $opposite = $primaryEffect === 'increase' ? 'decrease' : 'increase';

        return match ($transactionType) {
            'expense', 'asset' => $opposite,
            'income', 'revenue', 'liability', 'equity' => $primaryEffect,
            default => throw new InvalidArgumentException("Unsupported transaction type [{$transactionType}]."),
        };
    }
}
