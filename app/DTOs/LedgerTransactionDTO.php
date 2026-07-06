<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class LedgerTransactionDTO
{
    public function __construct(
        public int $farmerId,
        public int $farmId,
        public Carbon $date,
        public string $paymentMethod,
        public string $transactionType,
        public int $ledgerAccountId,
        public float $amount,
        public ?string $description,
        public ?string $referenceNumber,
        public string $transactionFor,
        public string $transactionUuid,
        public ?int $quantity,
        public ?float $unitCost,
        public ?string $uuid = null
    ) {}

    public static function fromRequest(array $validated, int $farmerId, int $farmId): self
    {
        $entry = $validated['entries'][0];

        return new self(
            farmerId: $farmerId,
            farmId: $farmId,
            date: Carbon::parse($validated['date']),
            paymentMethod: $validated['payment_method'],
            transactionType: $validated['type'],
            ledgerAccountId: (int) $entry['ledger_account_id'],
            amount: (float) $entry['amount'],
            description: $validated['description'] ?? null,
            referenceNumber: $validated['reference_number'] ?? null,
            transactionFor: $validated['transaction_for'],
            transactionUuid: $validated['transaction_uuid'],
            quantity: isset($entry['quantity']) ? (int) $entry['quantity'] : null,
            unitCost: isset($entry['unit_cost']) ? (float) $entry['unit_cost'] : null,
            uuid: $validated['uuid'] ?? null,
        );
    }
}
