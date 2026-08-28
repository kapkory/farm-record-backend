<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class TestImmutabledate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-immutabledate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::now();
        $tomorrow = $today->addDay();
        $this->line('Test immutabl edate');
        $this->line('Today: ' . $today->format('Y-m-d'));
        $this->line('Tomorrow: ' . $tomorrow->format('Y-m-d'));
    }
}
