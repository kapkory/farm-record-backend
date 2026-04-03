<?php

namespace App\Observers;

use App\Models\Core\AnimalEvent;
use App\Models\Core\AnimalGroup;

class AnimalEventObserver
{
    public function created(AnimalEvent $animalEvent): void
    {
        $this->syncGroupCount($animalEvent, 1);
    }

    public function deleted(AnimalEvent $animalEvent): void
    {
        $this->syncGroupCount($animalEvent, -1);
    }

    private function syncGroupCount(AnimalEvent $animalEvent, int $direction): void
    {
        if ($animalEvent->eventable_type !== AnimalGroup::class) {
            return;
        }

        $group = AnimalGroup::find($animalEvent->eventable_id);
        if (! $group) {
            return;
        }

        $quantity = max(1, (int) ($animalEvent->quantity ?? 1));
        $delta = match ($animalEvent->event_type) {
            'birth', 'purchase' => $quantity * $direction,
            'death', 'sale' => -1 * $quantity * $direction,
            default => 0,
        };

        if ($delta !== 0) {
            $group->current_count = max(0, $group->current_count + $delta);
            $group->saveQuietly();
        }
    }
}

