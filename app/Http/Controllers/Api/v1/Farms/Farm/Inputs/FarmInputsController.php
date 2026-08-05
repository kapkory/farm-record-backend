<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Inputs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inputs\StoreFarmInputRequest;
use App\Http\Requests\Inputs\StoreInputApplicationRequest;
use App\Http\Resources\Farms\Farm\Inputs\FarmInputResource;
use App\Http\Resources\Farms\Farm\Inputs\InputApplicationResource;
use App\Models\Core\Farm;
use App\Models\Core\FarmInput;
use App\Models\Core\InputApplication;
use App\Services\Inputs\InputApplicationService;
use App\Services\Inputs\InputPurchaseRecorder;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inputs bought in bulk and used across many animals — dip, drugs, feed.
 *
 * The purchase posts one expense to the ledger; applications draw stock down
 * and attribute a share of that already-posted cost to whatever they covered.
 */
class FarmInputsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function index(Request $request, ?string $farm_uuid = null): JsonResponse
    {
        $farmIds = Farm::farmerOwned($request->user()->id)->pluck('id');

        $query = FarmInput::with(['farm:id,uuid,name', 'treatmentType:id,name', 'applications'])
            ->whereIn('farm_id', $farmIds);

        if ($farm_uuid) {
            $farm = Farm::whereIn('id', $farmIds)->where('uuid', $farm_uuid)->first();
            if (! $farm) {
                return $this->errorResponse('Farm not found', 404);
            }
            $query->where('farm_id', $farm->id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        $inputs = $query->orderByDesc('purchase_date')->orderByDesc('id')->get();

        return $this->successResponse(FarmInputResource::collection($inputs), 'Inputs retrieved successfully');
    }

    public function store(StoreFarmInputRequest $request, InputPurchaseRecorder $recorder): JsonResponse
    {
        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            FarmInput::class,
            fn (FarmInput $input) => Farm::farmerOwned($request->user()->id)->where('id', $input->farm_id)->exists()
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(new FarmInputResource($this->loaded($existing)), 'Input already saved');
        }

        try {
            $farm = Farm::where('uuid', $request->validated('farm_uuid'))->firstOrFail();
            $quantity = (float) $request->validated('quantity');
            $totalCost = (float) $request->validated('total_cost');

            $input = FarmInput::create([
                'uuid' => $uuid,
                'farm_id' => $farm->id,
                'farmer_id' => $farm->farmer_id,
                'name' => $request->validated('name'),
                'category' => $request->validated('category'),
                'treatment_type_id' => $request->validated('treatment_type_id'),
                'quantity' => $quantity,
                'unit' => $request->validated('unit'),
                'quantity_remaining' => $quantity,
                'total_cost' => $totalCost,
                'unit_cost' => FarmInput::unitCostFor($totalCost, $quantity),
                'purchase_date' => $request->validated('purchase_date'),
                'supplier' => $request->validated('supplier'),
                'notes' => $request->validated('notes'),
                'user_id' => $request->user()->id,
            ]);

            // The money left when the input was bought, so it posts now — once.
            $recorder->record($request->user(), $input->load('farm'));

            return $this->successResponse(
                new FarmInputResource($this->loaded($input->fresh())),
                'Input saved successfully',
                201
            );
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, FarmInput::class, $uuid)) {
                return $this->successResponse(new FarmInputResource($this->loaded($replayed)), 'Input already saved');
            }

            return $this->errorResponse('Failed to save the input', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $input = $this->findOwned($request, $uuid);

        if (! $input) {
            return $this->errorResponse('Input not found', 404);
        }

        return $this->successResponse(new FarmInputResource($this->loaded($input)), 'Input retrieved successfully');
    }

    public function update(StoreFarmInputRequest $request, string $uuid): JsonResponse
    {
        $input = $this->findOwned($request, $uuid);

        if (! $input) {
            return $this->errorResponse('Input not found', 404);
        }

        try {
            $quantity = (float) $request->validated('quantity');
            $totalCost = (float) $request->validated('total_cost');
            $used = (float) $input->quantity - (float) $input->quantity_remaining;

            if ($quantity < $used) {
                return $this->errorResponse('Validation failed', 422, [
                    'quantity' => ["You have already used {$used} {$input->unit} of this input."],
                ]);
            }

            $input->update([
                'name' => $request->validated('name'),
                'category' => $request->validated('category'),
                'treatment_type_id' => $request->validated('treatment_type_id'),
                'quantity' => $quantity,
                'unit' => $request->validated('unit'),
                // Stock follows the corrected quantity; what is already used
                // stays used.
                'quantity_remaining' => round($quantity - $used, 3),
                'total_cost' => $totalCost,
                'unit_cost' => FarmInput::unitCostFor($totalCost, $quantity),
                'purchase_date' => $request->validated('purchase_date'),
                'supplier' => $request->validated('supplier'),
                'notes' => $request->validated('notes'),
            ]);

            return $this->successResponse(new FarmInputResource($this->loaded($input->fresh())), 'Input updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update the input', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $input = $this->findOwned($request, $uuid);

        if (! $input) {
            return $this->errorResponse('Input not found', 404);
        }

        if ($input->applications()->exists()) {
            return $this->errorResponse('Validation failed', 422, [
                'uuid' => ['This input has already been used. Reverse its applications first.'],
            ]);
        }

        $input->delete();

        return $this->successResponse(null, 'Input deleted successfully');
    }

    // POST /{uuid}/applications — record one use across many animals.
    public function apply(StoreInputApplicationRequest $request, string $uuid, InputApplicationService $service): JsonResponse
    {
        $input = $this->findOwned($request, $uuid);

        if (! $input) {
            return $this->errorResponse('Input not found', 404);
        }

        [$applicationUuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            InputApplication::class,
            fn (InputApplication $application) => $application->user_id === $request->user()->id
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse(
                new InputApplicationResource($existing->load('targets.targetable')),
                'Application already recorded'
            );
        }

        try {
            $application = $service->apply($input, $request->validated(), $request->user(), $applicationUuid);

            return $this->successResponse(
                new InputApplicationResource($application),
                'Application recorded successfully',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, InputApplication::class, $applicationUuid)) {
                return $this->successResponse(
                    new InputApplicationResource($replayed->load('targets.targetable')),
                    'Application already recorded'
                );
            }

            return $this->errorResponse('Failed to record the application', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function reverseApplication(Request $request, string $uuid, InputApplicationService $service): JsonResponse
    {
        $application = InputApplication::with('targets')->where('uuid', $uuid)->first();

        if (! $application || ! Farm::farmerOwned($request->user()->id)->where('id', $application->farm_id)->exists()) {
            return $this->errorResponse('Application not found', 404);
        }

        try {
            $service->reverse($application);

            return $this->successResponse(null, 'Application reversed successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to reverse the application', 500, ['exception' => $e->getMessage()]);
        }
    }

    protected function findOwned(Request $request, string $uuid): ?FarmInput
    {
        $input = FarmInput::where('uuid', $uuid)->first();

        if (! $input || ! Farm::farmerOwned($request->user()->id)->where('id', $input->farm_id)->exists()) {
            return null;
        }

        return $input;
    }

    protected function loaded(FarmInput $input): FarmInput
    {
        return $input->load([
            'farm:id,uuid,name',
            'treatmentType:id,name',
            'applications.targets.targetable',
        ]);
    }
}
