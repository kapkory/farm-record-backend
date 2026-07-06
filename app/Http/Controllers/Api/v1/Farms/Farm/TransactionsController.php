<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\DTOs\LedgerTransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreLedgerTransactionRequest;
use App\Http\Resources\Farms\Farm\LedgerTransactionResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\LedgerTransaction;
use App\Models\Core\Planting;
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
                $existing->load('entries.account', 'transactionable'),
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
                    $replayed->load('entries.account', 'transactionable'),
                    'Transaction already posted'
                );
            }

            throw $e;
        }

        return $this->successResponse($transaction, 'Transaction posted successfully', 201);
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

        return $this->successResponse(
            LedgerTransactionResource::collection($transactions),
            'Transactions retrieved successfully'
        );
    }

    protected function resolveFarmId(string $transactionFor, string $transactionUuid): int
    {
        return match ($transactionFor) {
            'planting' => Planting::query()->where('uuid', $transactionUuid)->value('farm_id'),
            'animal_group' => AnimalGroup::query()->where('uuid', $transactionUuid)->value('farm_id'),
            'animal' => Animal::query()->where('uuid', $transactionUuid)->value('farm_id')
                ?? throw new ModelNotFoundException,
            default => throw new InvalidArgumentException('Unsupported transaction target.'),
        };
    }

    /** @return class-string<Model> */
    protected function resolveTransactionableType(string $type): string
    {
        return match ($type) {
            'planting' => Planting::class,
            'animal_group' => AnimalGroup::class,
            'animal' => Animal::class,
            default => throw new InvalidArgumentException('Unsupported transaction target.'),
        };
    }
}
