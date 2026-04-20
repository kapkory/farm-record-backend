<?php

namespace App\Http\Resources\Farms\Farm;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalBreedingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $serviceDate      = $this->service_date ? Carbon::parse($this->service_date) : null;
        $expectedBirthDate = $this->expected_birth_date ? Carbon::parse($this->expected_birth_date) : null;

        return [
            'uuid'                      => $this->uuid,
            'farm_id'                   => $this->farm_id,
            'dam_id'                    => $this->whenLoaded('dam', fn () => $this->dam?->uuid, $this->dam_id),
            'dam'                       => $this->whenLoaded('dam', fn () => $this->dam ? [
                'uuid'        => $this->dam->uuid,
                'name'        => $this->dam->name,
                'tag_number'  => $this->dam->tag_number,
                'animal_type' => $this->dam->relationLoaded('animalType') ? [
                    'id'   => $this->dam->animalType?->id,
                    'name' => $this->dam->animalType?->name,
                ] : null,
                'breed'       => $this->dam->relationLoaded('animalBreed') ? [
                    'id'   => $this->dam->animalBreed?->id,
                    'name' => $this->dam->animalBreed?->name,
                ] : null,
            ] : null),
            'sire_id'                   => $this->whenLoaded('sire', fn () => $this->sire?->uuid, $this->sire_id),
            'sire'                      => $this->whenLoaded('sire', fn () => $this->sire ? [
                'uuid'        => $this->sire->uuid,
                'name'        => $this->sire->name,
                'tag_number'  => $this->sire->tag_number,
                'animal_type' => $this->sire->relationLoaded('animalType') ? [
                    'id'   => $this->sire->animalType?->id,
                    'name' => $this->sire->animalType?->name,
                ] : null,
                'breed'       => $this->sire->relationLoaded('animalBreed') ? [
                    'id'   => $this->sire->animalBreed?->id,
                    'name' => $this->sire->animalBreed?->name,
                ] : null,
            ] : null),
            'sire_type'                 => $this->sire_type,
            'service_date'              => $serviceDate?->toDateString(),
            'service_date_human'        => $serviceDate?->format('d M Y'),
            'expected_birth_date'       => $expectedBirthDate?->toDateString(),
            'expected_birth_date_human' => $expectedBirthDate?->format('d M Y'),
            'status'                    => $this->status,
            'days_until_birth'          => $this->days_until_birth,
            'is_overdue'                => $this->is_overdue,
            'ai_straw_code'             => $this->ai_straw_code,
            'ai_bull_name'              => $this->ai_bull_name,
            'ai_technician'             => $this->ai_technician,
            'notes'                     => $this->notes,
            'synced'                    => true,
            'created_at'                => $this->created_at?->toISOString(),
            'updated_at'                => $this->updated_at?->toISOString(),
        ];
    }
}

