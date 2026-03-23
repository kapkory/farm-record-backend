<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm\Crops;

use App\Http\Controllers\Controller;
use App\Models\Core\Planting;
use App\Models\Core\Treatment;
use App\Services\Treatment\TreatmentExpenseRecorder;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TreatmentsController extends Controller
{
    use ApiResponse;

    public function __construct(protected TreatmentExpenseRecorder $treatmentExpenseRecorder)
    {
    }

    public function listPlantingTreatments($plantingUuid): JsonResponse
    {
        $planting = Planting::query()->where('uuid', $plantingUuid)->firstOrFail();

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
            ->where('treatmentable_type', Planting::class)
            ->where('treatmentable_id', $planting->id)
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
            'planting_uuid' => 'required|uuid|exists:plantings,uuid',
            'farm_id' => 'nullable|uuid|exists:farms,uuid',
            'model' => 'nullable|string|in:planting',
            'treatment_type_id' => 'required|integer|exists:treatment_types,id',
            'details' => 'required|string|max:255',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'retreat_date' => 'nullable|date',
            'record_expense' => 'nullable|boolean',
            'expense_amount' => 'nullable|numeric|min:0.01|required_if:record_expense,true,1',
        ]);

        try {
            $planting = Planting::query()->where('uuid', $request->input('planting_uuid'))->firstOrFail();

            $treatment = DB::transaction(function () use ($request, $data, $planting) {
                $treatment = Treatment::create([
                    'uuid' => (string) Str::orderedUuid(),
                    'treatment_type_id' => $data['treatment_type_id'],
                    'farm_id' => $planting->farm_id,
                    'details' => $data['details'],
                    'treatmentable_type' => Planting::class,
                    'treatmentable_id' => $planting->id,
                    'date' => $data['date'],
                    'notes' => $data['notes'] ?? null,
                    'retreat_date' => $data['retreat_date'] ?? null,
                    'user_id' => $request->user()->id,
                ]);

                if (($data['record_expense'] ?? false) === true) {
                    $this->treatmentExpenseRecorder->recordForPlanting(
                        $request->user(),
                        $planting->load('farm'),
                        $data
                    );
                }

                return $treatment;
            });

            return $this->successResponse($treatment, 'Treatment saved successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save treatment', 500, ['exception' => $e->getMessage()]);
        }
    }
}
