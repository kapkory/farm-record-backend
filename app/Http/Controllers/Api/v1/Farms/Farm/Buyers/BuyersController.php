<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Buyers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreBuyerRequest;
use App\Http\Resources\Farms\Farm\BuyerResource;
use App\Models\Core\Buyer;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyersController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function listBuyers(Request $request): JsonResponse
    {
        $farmerIds = $request->user()->farmers()->pluck('farmers.id');

        $buyers = Buyer::query()
            ->whereIn('farmer_id', $farmerIds)
            ->orderBy('name')
            ->get();

        return $this->successResponse(BuyerResource::collection($buyers), 'Buyers retrieved successfully');
    }

    public function storeBuyer(StoreBuyerRequest $request): JsonResponse
    {
        $user = $request->user();
        $farmer = $user->farmers()->first();

        if (! $farmer) {
            return $this->errorResponse('No farmer profile is linked to the authenticated user.', 422);
        }

        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Buyer::class,
            fn (Buyer $buyer) => $user->farmers()->where('farmers.id', $buyer->farmer_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(new BuyerResource($existing), 'Buyer already saved');
        }

        try {
            $buyer = Buyer::create([
                'uuid' => $uuid,
                'farmer_id' => $farmer->id,
                'user_id' => $user->id,
                ...$request->validated(),
            ]);
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Buyer::class, $uuid)) {
                return $this->successResponse(new BuyerResource($replayed), 'Buyer already saved');
            }

            return $this->errorResponse('Failed to save buyer', 500, ['exception' => $e->getMessage()]);
        }

        return $this->successResponse(new BuyerResource($buyer), 'Buyer saved successfully', 201);
    }
}
