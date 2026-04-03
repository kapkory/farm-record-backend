<?php

namespace App\Http\Controllers\Api\v1\Farms\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farms\StoreAnimalEventRequest;
use App\Http\Resources\Farms\Farm\AnimalEventResource;
use App\Models\Core\Animal;
use App\Models\Core\AnimalEvent;
use App\Models\Core\AnimalGroup;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AnimalEventsController extends Controller
{
    use ApiResponse;

    public function store(StoreAnimalEventRequest $request): JsonResponse
    {
        try {
            $eventable = $this->resolveEventable($request->validated('eventable_type'), $request->validated('eventable_uuid'));

            $event = AnimalEvent::create([
                'uuid' => (string) Str::orderedUuid(),
                'eventable_type' => $eventable::class,
                'eventable_id' => $eventable->id,
                'event_type' => $request->validated('event_type'),
                'date' => $request->validated('date'),
                'quantity' => $request->validated('quantity') ?? null,
                'description' => $request->validated('description') ?? null,
                'metadata' => $request->validated('metadata') ?? null,
                'user_id' => $request->user()->id,
            ]);

            return $this->successResponse(new AnimalEventResource($event), 'Animal event saved successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to save animal event', 500, ['exception' => $e->getMessage()]);
        }
    }

    public function listEvents(string $uuid): JsonResponse
    {
        $target = AnimalGroup::where('uuid', $uuid)->first() ?: Animal::where('uuid', $uuid)->first();
        if (! $target) {
            return $this->errorResponse('Animal record not found', 404);
        }

        $events = AnimalEvent::query()
            ->where('eventable_type', $target::class)
            ->where('eventable_id', $target->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return $this->successResponse(AnimalEventResource::collection($events), 'Animal events retrieved successfully');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $event = AnimalEvent::where('uuid', $uuid)->first();
        if (! $event) {
            return $this->errorResponse('Animal event not found', 404);
        }

        try {
            $event->delete();
            return $this->successResponse(null, 'Animal event deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete animal event', 500, ['exception' => $e->getMessage()]);
        }
    }

    protected function resolveEventable(string $type, string $uuid): Model
    {
        return match ($type) {
            'animal_group' => AnimalGroup::where('uuid', $uuid)->firstOrFail(),
            'animal' => Animal::where('uuid', $uuid)->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported animal event target.'),
        };
    }
}

