<?php

namespace App\Services\Inputs;

use App\Models\Core\Animal;
use App\Models\Core\AnimalGroup;
use App\Models\Core\FarmInput;
use App\Models\Core\InputApplication;
use App\Models\Core\InputApplicationTarget;
use App\Models\Core\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records one use of a bulk input across many animals: draws the quantity out
 * of stock, splits its cost over everything it covered, and writes a Treatment
 * for each so the animals' health histories stay complete.
 *
 * Deliberately does NOT touch the ledger. The money was posted once when the
 * input was bought (InputPurchaseRecorder); what happens here is attribution of
 * an already-paid cost, not new spending. Posting again would double-count.
 */
class InputApplicationService
{
    public function __construct(protected CostAllocator $allocator) {}

    /**
     * @param  array{
     *     date: string, quantity_used: float, allocation_basis?: string,
     *     details?: string|null, notes?: string|null,
     *     targets: array<int, array{type: string, uuid: string, manual_cost?: float|null}>
     * }  $data
     */
    /**
     * @param  bool  $createTreatment  Standalone applications write a Treatment
     *   per target so health histories stay complete. Pass false when the
     *   caller already owns the record (a treatment or task that drew this
     *   input from stock) so a second health record isn't created.
     */
    public function apply(FarmInput $input, array $data, User $user, string $uuid, bool $createTreatment = true): InputApplication
    {
        return DB::transaction(function () use ($input, $data, $user, $uuid, $createTreatment) {
            $input = FarmInput::whereKey($input->id)->lockForUpdate()->firstOrFail();

            $quantityUsed = round((float) $data['quantity_used'], 3);
            $basis = $data['allocation_basis'] ?? InputApplication::BASIS_PER_HEAD;

            // Re-checked inside the lock, not just in the form request: two
            // dippings recorded at once must not both pass a stock check that
            // only one of them can actually satisfy.
            if ($quantityUsed > (float) $input->quantity_remaining) {
                throw ValidationException::withMessages([
                    'quantity_used' => [sprintf(
                        'Only %s %s of %s is left.',
                        rtrim(rtrim(number_format((float) $input->quantity_remaining, 3, '.', ''), '0'), '.'),
                        $input->unit,
                        $input->name
                    )],
                ]);
            }

            $subjects = $this->resolveTargets($input, $data['targets'] ?? []);
            $totalCost = round($quantityUsed * (float) $input->unit_cost, 2);

            $application = InputApplication::create([
                'uuid' => $uuid,
                'farm_input_id' => $input->id,
                'farm_id' => $input->farm_id,
                'date' => $data['date'],
                'quantity_used' => $quantityUsed,
                'total_cost' => $totalCost,
                'allocation_basis' => $basis,
                'details' => $data['details'] ?: $input->name,
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->id,
            ]);

            $shares = $this->allocator->allocate($totalCost, array_map(
                fn (array $s) => [
                    'head_count' => $s['head_count'],
                    'basis_value' => $s['basis_value'],
                    'manual_cost' => $s['manual_cost'],
                ],
                $subjects
            ), $basis);

            foreach ($subjects as $index => $subject) {
                $share = $shares[$index] ?? ['allocated_cost' => 0.0];

                // When the caller already owns the record (a treatment/task that
                // drew this input from stock), skip the auto-created health
                // record — and leave treatment_id null so reversing the stock
                // usage never deletes a deliberately-recorded treatment.
                $treatment = $createTreatment
                    ? $this->recordTreatment($input, $subject['model'], $application)
                    : null;

                InputApplicationTarget::create([
                    'uuid' => (string) Str::orderedUuid(),
                    'input_application_id' => $application->id,
                    // FQCN, matching TreatmentsController and the transactions
                    // list, so these rows join up with the rest of the app.
                    'targetable_type' => $subject['model']::class,
                    'targetable_id' => $subject['model']->id,
                    'head_count' => $subject['head_count'],
                    'basis_value' => $subject['basis_value'],
                    'allocated_cost' => $share['allocated_cost'],
                    'treatment_id' => $treatment?->id,
                ]);
            }

            $input->quantity_remaining = round((float) $input->quantity_remaining - $quantityUsed, 3);
            $input->saveQuietly();

            return $application->load('targets.targetable');
        });
    }

    /** Undo an application: put the stock back and remove what it wrote. */
    public function reverse(InputApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $input = FarmInput::whereKey($application->farm_input_id)->lockForUpdate()->first();

            foreach ($application->targets as $target) {
                if ($target->treatment_id) {
                    Treatment::whereKey($target->treatment_id)->delete();
                }
                $target->delete();
            }

            if ($input) {
                $input->quantity_remaining = round(
                    min((float) $input->quantity + 0.0, (float) $input->quantity_remaining + (float) $application->quantity_used),
                    3
                );
                $input->saveQuietly();
            }

            $application->delete();
        });
    }

    /**
     * Turn the submitted target list into models with the numbers the split
     * needs. A group counts for its current head; an animal counts for one.
     *
     * @return array<int, array{model: Model, head_count: int, basis_value: float|null, manual_cost: float|null}>
     */
    protected function resolveTargets(FarmInput $input, array $targets): array
    {
        if ($targets === []) {
            throw ValidationException::withMessages([
                'targets' => ['Choose at least one animal or group this covered.'],
            ]);
        }

        $resolved = [];

        foreach ($targets as $target) {
            $model = match ($target['type'] ?? null) {
                'animal_group' => AnimalGroup::with('latestWeight')->where('uuid', $target['uuid'])->first(),
                'animal' => Animal::with('latestWeight')->where('uuid', $target['uuid'])->first(),
                default => null,
            };

            if (! $model || (int) $model->farm_id !== (int) $input->farm_id) {
                throw ValidationException::withMessages([
                    'targets' => ['One of the selected animals is not on this farm.'],
                ]);
            }

            $resolved[] = [
                'model' => $model,
                'head_count' => $model instanceof AnimalGroup ? max(1, (int) $model->current_count) : 1,
                // Weight per head — group readings are already per head.
                'basis_value' => $model->latestWeight?->weight_kg !== null
                    ? (float) $model->latestWeight->weight_kg
                    : null,
                'manual_cost' => isset($target['manual_cost']) ? (float) $target['manual_cost'] : null,
            ];
        }

        return $resolved;
    }

    /**
     * The health record. Written without any expense flag — the cost is carried
     * by the input allocation, and recording it here too would double-count.
     */
    protected function recordTreatment(FarmInput $input, Model $subject, InputApplication $application): ?Treatment
    {
        if (! $input->treatment_type_id) {
            return null;
        }

        return Treatment::create([
            'uuid' => (string) Str::orderedUuid(),
            'treatment_type_id' => $input->treatment_type_id,
            'farm_id' => $input->farm_id,
            'details' => $application->details,
            'treatmentable_type' => $subject::class,
            'treatmentable_id' => $subject->id,
            'date' => Carbon::parse($application->date)->toDateString(),
            'notes' => sprintf('%s applied from stock (%s).', $input->name, $application->uuid),
            'user_id' => $application->user_id,
        ]);
    }
}
