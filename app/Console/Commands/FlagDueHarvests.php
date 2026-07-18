<?php

namespace App\Console\Commands;

use App\Models\Core\Hive;
use App\Models\Core\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FlagDueHarvests extends Command
{
    protected $signature = 'bees:flag-due-harvests';

    protected $description = 'Create a harvest reminder task for every occupied hive whose next harvest is due';

    public function handle(): int
    {
        $morphClass = (new Hive)->getMorphClass();
        $created = 0;

        Hive::query()
            ->where('occupancy', Hive::OCCUPANCY_OCCUPIED)
            ->whereNotNull('next_harvest_due')
            ->whereDate('next_harvest_due', '<=', now()->toDateString())
            ->with('apiary')
            ->chunkById(200, function ($hives) use ($morphClass, &$created) {
                foreach ($hives as $hive) {
                    $hasOpenTask = Task::query()
                        ->where('taskable_type', $morphClass)
                        ->where('taskable_id', $hive->id)
                        ->whereNotIn('task_status', [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED])
                        ->exists();

                    if ($hasOpenTask) {
                        continue;
                    }

                    Task::create([
                        'uuid' => (string) Str::orderedUuid(),
                        'title' => sprintf(
                            'Harvest hive %s%s',
                            $hive->code,
                            $hive->apiary ? " ({$hive->apiary->name})" : ''
                        ),
                        'description' => 'This hive is due for a honey harvest.',
                        'user_id' => $hive->user_id,
                        'due_date' => $hive->next_harvest_due,
                        'priority' => Task::PRIORITY_MEDIUM,
                        'task_status' => Task::STATUS_PENDING,
                        'taskable_type' => $morphClass,
                        'taskable_id' => $hive->id,
                    ]);

                    $created++;
                }
            });

        $this->info("Created {$created} harvest reminder task(s).");

        return self::SUCCESS;
    }
}
