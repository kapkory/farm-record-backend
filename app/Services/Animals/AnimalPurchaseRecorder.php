<?php

namespace App\Services\Animals;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\Animal;
use App\Models\Core\LedgerAccount;
use App\Models\User;
use App\Services\Ledger\LedgerTransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Posts what a farmer paid for an animal to the ledger, so the purchase turns
 * up in the animal's Costs tab and in expense reports instead of sitting in a
 * column nobody reads.
 *
 * Follows TreatmentExpenseRecorder / ProductionExpenseRecorder, with one
 * deliberate difference: those throw when no account is found, because the
 * farmer explicitly asked to record an expense. Here the expense is a side
 * effect of saving an animal, so a missing ledger account must never cost the
 * farmer the animal record — it is logged and skipped.
 */
class AnimalPurchaseRecorder
{
    /** Best account first; the ledger is seeded, but old farms may lag. */
    protected const PREFERRED_ACCOUNTS = ['Livestock Purchases', 'General Expenses'];

    public function __construct(protected LedgerTransactionService $ledgerTransactionService) {}

    public function record(User $user, Animal $animal): void
    {
        $amount = (float) ($animal->purchase_price ?? 0);

        // Only a purchase is a cost. An animal born on the farm or donated has
        // no acquisition price to post, whatever is sitting in the column.
        if ($amount <= 0 || $animal->acquisition_type !== 'purchased') {
            return;
        }

        $farm = $animal->relationLoaded('farm') ? $animal->farm : $animal->farm()->first();

        if (! $farm) {
            return;
        }

        $account = LedgerAccount::query()
            ->where('type', 'expense')
            ->whereIn('name', self::PREFERRED_ACCOUNTS)
            ->where(function ($query) use ($farm) {
                $query->whereNull('farmer_id')->orWhere('farmer_id', $farm->farmer_id);
            })
            ->orderByRaw("CASE name WHEN 'Livestock Purchases' THEN 1 WHEN 'General Expenses' THEN 2 ELSE 99 END")
            ->orderByDesc('farmer_id')
            ->first();

        if (! $account) {
            Log::warning('No livestock purchase ledger account found; purchase not posted.', [
                'animal_uuid' => $animal->uuid,
                'amount' => $amount,
            ]);

            return;
        }

        $label = $animal->name ?: $animal->tag_number ?: 'animal';

        $dto = new LedgerTransactionDTO(
            farmerId: (int) $farm->farmer_id,
            farmId: (int) $farm->id,
            date: Carbon::parse($animal->acquisition_date ?? $animal->created_at ?? now()),
            paymentMethod: 'cash',
            transactionType: 'expense',
            ledgerAccountId: $account->id,
            amount: $amount,
            description: "Purchase of {$label}",
            referenceNumber: null,
            transactionFor: 'animal',
            transactionUuid: $animal->uuid,
            quantity: 1,
            unitCost: $amount,
        );

        try {
            $this->ledgerTransactionService->store($user, $dto);
        } catch (\Throwable $e) {
            // Same reasoning as above: the animal is saved either way.
            Log::warning('Failed to post an animal purchase to the ledger.', [
                'animal_uuid' => $animal->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
