<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\DTOs\LedgerTransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreLedgerTransactionRequest;
use App\Http\Resources\Farms\Farm\LedgerTransactionResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\LedgerTransaction;
use App\Models\Core\Planting;
use App\Models\Core\Sale;
use App\Services\Ledger\LedgerTransactionService;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class TransactionsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function __construct(protected LedgerTransactionService $ledgerTransactionService) {}

    public function storeTransaction(StoreLedgerTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $farmer = $request->user()->farmers()->first();

        if (! $farmer) {
            return $this->errorResponse('No farmer profile is linked to the authenticated user.', 422);
        }

        // A replayed offline create must be answered from the stored row
        // before the ledger service runs, or money would be posted twice.
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            LedgerTransaction::class,
            fn (LedgerTransaction $transaction) => $user->farmers()->where('farmers.id', $transaction->farmer_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                LedgerTransactionResource::make($existing->load('entries.account', 'transactionable')),
                'Transaction already posted'
            );
        }

        $validated = $request->validated();
        $farmId = $this->resolveFarmId($validated['transaction_for'], $validated['transaction_uuid']);
        $farm = Farm::findOrFail($farmId);
        $dto = LedgerTransactionDTO::fromRequest($validated, $farm->farmer_id, $farmId);

        try {
            $transaction = $this->ledgerTransactionService->store($user, $dto);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, LedgerTransaction::class, $uuid)) {
                return $this->successResponse(
                    LedgerTransactionResource::make($replayed->load('entries.account', 'transactionable')),
                    'Transaction already posted'
                );
            }

            throw $e;
        }

        return $this->successResponse(
            LedgerTransactionResource::make($transaction),
            'Transaction posted successfully',
            201
        );
    }

    public function listTransactions(string $transactionable_type, $transactionable_uuid): JsonResponse
    {
        $user = request()->user();
        $farmerIds = $user->farmers()->pluck('farmers.id');

        $modelClass = $this->resolveTransactionableType($transactionable_type);
        $transactionable = $modelClass::where('uuid', $transactionable_uuid)->firstOrFail();

        $transactions = LedgerTransaction::query()
            ->with([
                'transactionable',
                'entries' => fn ($query) => $query->with('account')->orderBy('id')->limit(1),
            ])
            ->where('transactionable_type', $modelClass)
            ->where('transactionable_id', $transactionable->id)
            ->whereIn('farmer_id', $farmerIds)
            ->latest('date')
            ->latest('id')
            ->get();

        $direct = LedgerTransactionResource::collection($transactions)
            ->toArray(request());

        // Costs that were never posted against this animal directly: its share
        // of a bulk input the whole farm draws on — a tin of dip, a bag of feed.
        // The ledger holds one posting against the input; these rows say who
        // benefited. Merged in here so the Costs view shows the true cost of
        // keeping this animal rather than only what was billed straight to it.
        $shared = $this->sharedInputCosts($transactionable);

        $rows = collect($direct)
            ->map(fn (array $row) => $row + ['source' => 'direct'])
            ->concat($shared)
            ->sortByDesc(fn (array $row) => [$row['date'] ?? '', $row['id'] ?? 0])
            ->values();

        return $this->successResponse($rows, 'Transactions retrieved successfully');
    }

    protected function resolveFarmId(string $transactionFor, string $transactionUuid): int
    {
        return match ($transactionFor) {
            'farm' => \App\Models\Core\Farm::query()->where('uuid', $transactionUuid)->value('id')
                ?? throw new ModelNotFoundException,
            'planting' => Planting::query()->where('uuid', $transactionUuid)->value('farm_id'),
            'animal_group' => AnimalGroup::query()->where('uuid', $transactionUuid)->value('farm_id'),
            'animal' => Animal::query()->where('uuid', $transactionUuid)->value('farm_id')
                ?? throw new ModelNotFoundException,
            'hive' => Hive::query()->where('uuid', $transactionUuid)->value('farm_id')
                ?? throw new ModelNotFoundException,
            default => throw new InvalidArgumentException('Unsupported transaction target.'),
        };
    }

    /** @return class-string<Model> */
    protected function resolveTransactionableType(string $type): string
    {
        return match ($type) {
            'farm' => \App\Models\Core\Farm::class,
            'planting' => Planting::class,
            'animal_group' => AnimalGroup::class,
            'animal' => Animal::class,
            'hive' => Hive::class,
            'sale' => Sale::class,
            default => throw new InvalidArgumentException('Unsupported transaction target.'),
        };
    }

    /**
     * This record's shares of bulk inputs, shaped like ledger rows so the Costs
     * view can render both from one list.
     *
     * These are attributions, not postings — the money already appears in the
     * ledger once, against the input purchase. Summing `direct` and
     * `shared_input` rows together gives the cost of this animal; summing them
     * across every animal would double-count the purchase, which is why they
     * carry a `source` the caller can group by.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function sharedInputCosts(Model $transactionable): \Illuminate\Support\Collection
    {
        if (! method_exists($transactionable, 'inputAllocations')) {
            return collect();
        }

        return $transactionable->inputAllocations()
            ->with(['application.farmInput'])
            ->get()
            ->filter(fn ($target) => $target->application !== null)
            ->map(function ($target) {
                $application = $target->application;
                $input = $application->farmInput;
                $unit = $input?->unit ?? 'unit';

                return [
                    'id' => null,
                    'uuid' => $target->uuid,
                    'date' => $application->date?->toDateString(),
                    'payment_method' => null,
                    'description' => sprintf(
                        '%s — %s (share of %s %s)',
                        $input?->name ?? 'Farm input',
                        $application->details,
                        rtrim(rtrim(number_format((float) $application->quantity_used, 3, '.', ''), '0'), '.'),
                        $unit
                    ),
                    'reference_number' => null,
                    'transaction_for' => 'farm_input',
                    'transaction_uuid' => $input?->uuid,
                    'amount' => (float) $target->allocated_cost,
                    'entry_type' => 'debit',
                    'account_name' => 'Shared farm input',
                    'ledger_entries' => [],
                    'source' => 'shared_input',
                    'created_at' => $target->created_at?->toISOString(),
                    'updated_at' => $target->updated_at?->toISOString(),
                ];
            })
            ->values();
    }
}
