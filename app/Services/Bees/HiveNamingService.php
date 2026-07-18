<?php

namespace App\Services\Bees;

use App\Models\Core\AnimalGroup;
use App\Models\Core\ApiaryProfile;
use Illuminate\Support\Facades\DB;

class HiveNamingService
{
    /**
     * Allocate the next code for an apiary. Locks the profile row so two
     * concurrent creates cannot be handed the same sequence.
     *
     * @return array{sequence: int, code: string}
     */
    public function allocate(AnimalGroup $apiary): array
    {
        return DB::transaction(function () use ($apiary) {
            ApiaryProfile::firstOrCreate(['animal_group_id' => $apiary->id]);

            $profile = ApiaryProfile::query()
                ->where('animal_group_id', $apiary->id)
                ->lockForUpdate()
                ->first();

            $sequence = $profile->next_sequence;
            $code = $this->codeFor($sequence, $profile->naming_prefix, $profile->naming_scheme);

            $profile->next_sequence = $sequence + 1;
            $profile->save();

            return ['sequence' => $sequence, 'code' => $code];
        });
    }

    /**
     * alpha:   1 => A … 26 => Z, 27 => 1A … 52 => 1Z, 53 => 2A …
     * numeric: 1 => 1, 2 => 2 …
     * A prefix ("KB") is prepended with a dash: KB-A, KB-1A, KB-7 …
     */
    public function codeFor(int $sequence, ?string $prefix, string $scheme = ApiaryProfile::SCHEME_ALPHA): string
    {
        $body = $scheme === ApiaryProfile::SCHEME_NUMERIC
            ? (string) $sequence
            : $this->alphaCode($sequence);

        $prefix = strtoupper(trim((string) $prefix));

        return $prefix === '' ? $body : "{$prefix}-{$body}";
    }

    protected function alphaCode(int $sequence): string
    {
        $cycle = intdiv($sequence - 1, 26);
        $letter = chr(ord('A') + (($sequence - 1) % 26));

        return ($cycle > 0 ? $cycle : '').$letter;
    }
}
