<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\DTOs\LedgerTransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreLedgerTransactionRequest;
use App\Models\Core\Farm;
use App\Models\Core\Farmer;
use App\Services\Ledger\LedgerTransactionService;
use App\Traits\ApiResponse;
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

    public function listTransactions(): JsonResponse
    {
        return $this->successResponse([], 'Transactions retrieved successfully');
    }

    protected function resolveFarmId(string $transactionFor, string $transactionUuid): int
    {
        return match ($transactionFor) {
            'planting' => \App\Models\Core\Planting::query()->where('uuid', $transactionUuid)->value('farm_id')
                ?? throw new ModelNotFoundException(),
            default => throw new InvalidArgumentException('Unsupported transaction target.'),
        };
    }
}
