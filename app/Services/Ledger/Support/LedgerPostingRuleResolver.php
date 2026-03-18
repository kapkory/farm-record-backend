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

    public function effectForPrimaryAccount(string $transactionType): string
    {
        return match ($transactionType) {
            'expense', 'asset' => 'increase',
            'income', 'revenue', 'liability', 'equity' => 'increase',
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

    public function contraEffect(string $transactionType): string
    {
        return match ($transactionType) {
            'expense', 'asset' => 'decrease',
            'income', 'revenue', 'liability', 'equity' => 'increase',
            default => throw new InvalidArgumentException("Unsupported transaction type [{$transactionType}]."),
        };
    }
}

