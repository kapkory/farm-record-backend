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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class TransactionsController extends Controller
{
    use ApiResponse;

    public function __construct(protected LedgerTransactionService $ledgerTransactionService)
    {
    }

    public function storeTransaction(StoreLedgerTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $farmer = $request->user()->farmers()->first();

        if (! $farmer) {
            return $this->errorResponse('No farmer profile is linked to the authenticated user.', 422);
        }

        $validated = $request->validated();
        $farmId = $this->resolveFarmId($validated['transaction_for'], $validated['transaction_uuid']);
        $farm = Farm::findOrFail($farmId);
        $dto = LedgerTransactionDTO::fromRequest($validated, $farm->farmer_id, $farmId);

        $transaction = $this->ledgerTransactionService->store($user, $dto);

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
            'planting' => \App\Models\Core\Planting::query()->where('uuid', $transactionUuid)->value('farm_id'),
            'animal_group' => \App\Models\Core\AnimalGroup::query()->where('uuid', $transactionUuid)->value('farm_id'),
            'animal' => \App\Models\Core\Animal::query()->where('uuid', $transactionUuid)->value('farm_id')
                ?? throw new ModelNotFoundException(),
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
