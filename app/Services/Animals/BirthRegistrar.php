<?php

namespace App\Services\Animals;

use App\Models\Core\Animal;
use App\Models\Core\AnimalBreeding;
use App\Models\Core\AnimalEvent;
use App\Models\Core\AnimalGroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a pending pregnancy into a recorded birth in one transaction:
 * a birth AnimalEvent on the dam, one Animal per live offspring with its
 * parentage already filled in, and the breeding closed as `born`.
 *
 * Closing the pregnancy fires AnimalBreedingObserver, which completes the
 * "expected birth" reminder task — that isn't done here.
 *
 * Every record is keyed on a uuid the client generated, so replaying the
 * request (the offline queue retrying a create) returns the stored result
 * instead of producing a second litter.
 */
class BirthRegistrar
{
    /**
     * @param  array{
     *     birth_date: string,
     *     stillborn_count?: int|null,
     *     inherit_treatment_plan?: bool,
     *     offspring?: array<int, array<string, mixed>>
     * }  $data
     */
    public function register(AnimalBreeding $breeding, array $data, User $user, string $eventUuid): AnimalEvent
    {
        return DB::transaction(function () use ($breeding, $data, $user, $eventUuid) {
            $dam = Animal::with(['animalGroup', 'animalType', 'animalBreed'])->findOrFail($breeding->dam_id);

            $birthDate = Carbon::parse($data['birth_date'])->startOfDay();
            $offspringRows = array_values($data['offspring'] ?? []);
            $stillbornCount = (int) ($data['stillborn_count'] ?? 0);
            $liveCount = count($offspringRows);

            $event = AnimalEvent::create([
                'uuid' => $eventUuid,
                // FQCN rather than the morph alias, matching how
                // AnimalEventsController stores and reads animal events.
                'eventable_type' => $dam::class,
                'eventable_id' => $dam->id,
                'event_type' => 'birth',
                'date' => $birthDate->toDateString(),
                'quantity' => $liveCount,
                'description' => $this->describe($liveCount, $stillbornCount),
                'metadata' => [
                    'breeding_uuid' => $breeding->uuid,
                    'stillborn_count' => $stillbornCount,
                    'sire_type' => $breeding->sire_type,
                ],
                'user_id' => $user->id,
            ]);

            foreach ($offspringRows as $row) {
                $this->createOffspring($breeding, $dam, $row, $birthDate, $user, (bool) ($data['inherit_treatment_plan'] ?? false));
            }

            $breeding->forceFill([
                'status' => 'born',
                'actual_birth_date' => $birthDate->toDateString(),
                'offspring_count' => $liveCount,
                'stillborn_count' => $stillbornCount,
                'birth_event_id' => $event->id,
            ])->save();

            $this->bumpGroupCount($dam, $liveCount);

            return $event;
        });
    }

    /**
     * One newborn. The dam supplies everything the farmer would otherwise
     * retype — farm, group, species, breed — and the pregnancy supplies the
     * sire, so the pedigree the InbreedingChecker walks is recorded for free.
     */
    protected function createOffspring(
        AnimalBreeding $breeding,
        Animal $dam,
        array $row,
        Carbon $birthDate,
        User $user,
        bool $inheritTreatmentPlan
    ): Animal {
        $uuid = $row['uuid'] ?? (string) Str::orderedUuid();

        // A partially-applied replay can leave some offspring already stored.
        if ($existing = Animal::where('uuid', $uuid)->first()) {
            return $existing;
        }

        $tagNumber = $row['tag_number'] ?? null;
        $tagNumber = $tagNumber !== null && $tagNumber !== '' ? $tagNumber : Animal::generateTagNumber();
        $name = $row['name'] ?? null;

        return Animal::create([
            'uuid' => $uuid,
            'farm_id' => $dam->farm_id,
            'farmer_id' => $dam->farmer_id,
            'animal_group_id' => $dam->animal_group_id,
            'animal_type_id' => $dam->animal_type_id,
            'animal_breed_id' => $row['animal_breed_id'] ?? $dam->animal_breed_id,
            'dam_id' => $dam->id,
            'sire_id' => $breeding->sire_id,
            'animal_breeding_id' => $breeding->id,
            'treatment_plan_id' => $inheritTreatmentPlan ? $dam->treatment_plan_id : null,
            'tag_number' => $tagNumber,
            'name' => $name !== null && $name !== '' ? $name : $tagNumber,
            'gender' => $row['gender'] ?? 'unknown',
            'date_of_birth' => $birthDate->toDateString(),
            'acquisition_date' => $birthDate->toDateString(),
            'acquisition_type' => 'born',
            'status' => 'active',
            'notes' => $row['notes'] ?? null,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Where the dam belongs to a group, its head count has to grow by the
     * live offspring. AnimalEventObserver already does this for birth events
     * — but only when the *event itself* hangs off the group, and ours hangs
     * off the dam so it stays in her history. Bumping here is the only path;
     * don't add a second one in the observer or the count will double.
     */
    protected function bumpGroupCount(Animal $dam, int $liveCount): void
    {
        if (! $dam->animal_group_id || $liveCount < 1) {
            return;
        }

        $group = AnimalGroup::find($dam->animal_group_id);

        if (! $group) {
            return;
        }

        $group->current_count = max(0, (int) $group->current_count + $liveCount);
        $group->saveQuietly();
    }

    protected function describe(int $liveCount, int $stillbornCount): string
    {
        $parts = [$liveCount === 1 ? '1 live offspring' : "{$liveCount} live offspring"];

        if ($stillbornCount > 0) {
            $parts[] = $stillbornCount === 1 ? '1 stillborn' : "{$stillbornCount} stillborn";
        }

        return 'Birth recorded: '.implode(', ', $parts).'.';
    }
}
