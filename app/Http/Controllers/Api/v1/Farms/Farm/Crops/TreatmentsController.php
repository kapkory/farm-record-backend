<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Crops;

use App\Http\Controllers\Controller;
use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\FarmInput;
use App\Models\Core\Planting;
use App\Models\Core\Treatment;
use App\Services\Inputs\InputApplicationService;
use App\Services\Treatment\TreatmentExpenseRecorder;
use App\Traits\ApiResponse;
use App\Traits\ResolvesClientUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TreatmentsController extends Controller
{
    use ApiResponse, ResolvesClientUuid;

    public function __construct(
        protected TreatmentExpenseRecorder $treatmentExpenseRecorder,
        protected InputApplicationService $inputApplicationService,
    ) {}

    public function listPlantingTreatments($plantingUuid): JsonResponse
    {
        $model = request()->query('model', 'planting');
        $treatmentable = $this->resolveTreatmentable($model, $plantingUuid);

        $treatments = Treatment::query()
            ->leftJoin('treatment_types', 'treatment_types.id', '=', 'treatments.treatment_type_id')
            ->select(
                'treatments.uuid',
                'treatments.farm_id',
                'treatments.treatment_type_id',
                'treatments.details',
                'treatments.date',
                'treatments.notes',
                'treatments.retreat_date',
                'treatments.created_at',
                'treatment_types.name as treatment_type',
                'treatment_types.type as type'
            )
            ->where('treatmentable_type', $treatmentable::class)
            ->where('treatmentable_id', $treatmentable->id)
            ->orderBy('treatments.created_at')
            ->get()
            ->map(function ($treatment) {
                $parsed = $treatment->date ? Carbon::parse($treatment->date) : null;
                $parsedRetreat = $treatment->retreat_date ? Carbon::parse($treatment->retreat_date) : null;

                $treatment->date = $parsed?->toDateString();
                $treatment->date_human = $parsed?->diffForHumans();
                $treatment->retreat_date = $parsedRetreat?->toDateString();
                $treatment->retreat_date_human = $parsedRetreat?->diffForHumans();
                $treatment->created_at = Carbon::parse($treatment->created_at)->toDateTimeString();

                return $treatment;
            })
            ->values();

        return $this->successResponse($treatments, 'Treatments retrieved successfully', 200);
    }

    public function storeTreatment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'nullable|uuid',
            'planting_uuid' => 'nullable|uuid|exists:plantings,uuid',
            'animal_group_uuid' => 'nullable|uuid|exists:animal_groups,uuid',
            'animal_uuid' => 'nullable|uuid|exists:animals,uuid',
            'farm_id' => 'nullable|uuid|exists:farms,uuid',
            'model' => 'nullable|string|in:planting,animal_group,animal',
            'treatment_type_id' => 'required|integer|exists:treatment_types,id',
            'details' => 'required|string|max:255',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'retreat_date' => 'nullable|date',
            'record_expense' => 'nullable|boolean',
            'expense_amount' => 'nullable|numeric|min:0.01|required_if:record_expense,true,1',
            // Optionally draw the treatment from bulk stock: records a stock
            // usage tied to this treatment, which handles the cost. Livestock
            // only — input applications target animals/groups, not plantings.
            'input_uuid' => 'nullable|uuid|exists:farm_inputs,uuid',
            'input_quantity_used' => 'nullable|numeric|gt:0|required_with:input_uuid',
        ]);

        [$uuid, $existing, $foreign] = $this->resolveClientUuid(
            $request,
            Treatment::class,
            fn (Treatment $treatment) => $treatment->user_id === $request->user()->id
        );

        if ($foreign) {
            return $this->clientUuidTakenResponse();
        }

        if ($existing) {
            return $this->successResponse($existing, 'Treatment already saved');
        }

        try {
            $model = $data['model'] ?? 'planting';
            $targetUuid = match ($model) {
                'animal_group' => $request->input('animal_group_uuid'),
                'animal' => $request->input('animal_uuid'),
                default => $request->input('planting_uuid'),
            };

            if (! $targetUuid) {
                return $this->errorResponse('A valid target UUID is required for the selected treatment model.', 422);
            }

            $treatmentable = $this->resolveTreatmentable($model, $targetUuid);

            $usingInput = ! empty($data['input_uuid']);

            if ($usingInput && $model === 'planting') {
                return $this->errorResponse('Validation failed', 422, [
                    'input_uuid' => ['Using bulk stock is only available for livestock treatments.'],
                ]);
            }

            $input = null;
            if ($usingInput) {
                $input = FarmInput::where('uuid', $data['input_uuid'])->first();
                if (! $input || (int) $input->farm_id !== (int) $treatmentable->farm_id) {
                    return $this->errorResponse('Validation failed', 422, [
                        'input_uuid' => ['The selected input is not on this farm.'],
                    ]);
                }
            }

            $treatment = DB::transaction(function () use ($request, $data, $treatmentable, $model, $uuid, $usingInput, $input, $targetUuid) {
                $treatment = Treatment::create([
                    'uuid' => $uuid,
                    'treatment_type_id' => $data['treatment_type_id'],
                    'farm_id' => $treatmentable->farm_id,
                    'details' => $data['details'],
                    'treatmentable_type' => $treatmentable::class,
                    'treatmentable_id' => $treatmentable->id,
                    'date' => $data['date'],
                    'notes' => $data['notes'] ?? null,
                    'retreat_date' => $data['retreat_date'] ?? null,
                    'user_id' => $request->user()->id,
                ]);

                // Drawing from stock carries the cost via the input allocation,
                // so the manual expense is skipped to avoid double-counting.
                if ($usingInput && $input) {
                    $this->inputApplicationService->apply(
                        $input,
                        [
                            'date' => $data['date'],
                            'quantity_used' => (float) $data['input_quantity_used'],
                            'allocation_basis' => 'per_head',
                            'details' => $data['details'],
                            'notes' => $data['notes'] ?? null,
                            'targets' => [['type' => $model, 'uuid' => $targetUuid]],
                        ],
                        $request->user(),
                        (string) Str::orderedUuid(),
                        createTreatment: false,
                    );
                } elseif (($data['record_expense'] ?? false) === true) {
                    if ($model === 'animal_group' && $treatmentable instanceof AnimalGroup) {
                        $this->treatmentExpenseRecorder->recordForAnimalGroup($request->user(), $treatmentable->load('farm'), $data);
                    }

                    if ($model === 'animal' && $treatmentable instanceof Animal) {
                        $this->treatmentExpenseRecorder->recordForAnimal($request->user(), $treatmentable->load('farm'), $data);
                    }

                    if ($model === 'planting' && $treatmentable instanceof Planting) {
                        $this->treatmentExpenseRecorder->recordForPlanting($request->user(), $treatmentable->load('farm'), $data);
                    }
                }

                return $treatment;
            });

            return $this->successResponse($treatment, 'Treatment saved successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Throwable $e) {
            if ($replayed = $this->findAfterUniqueViolation($e, Treatment::class, $uuid)) {
                return $this->successResponse($replayed, 'Treatment already saved');
            }

            return $this->errorResponse('Failed to save treatment', 500, ['exception' => $e->getMessage()]);
        }
    }

    protected function resolveTreatmentable(string $type, string $uuid): Model
    {
        return match ($type) {
            'planting' => Planting::query()->where('uuid', $uuid)->firstOrFail(),
            'animal_group' => AnimalGroup::query()->where('uuid', $uuid)->firstOrFail(),
            'animal' => Animal::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported treatment target.'),
        };
    }
}
