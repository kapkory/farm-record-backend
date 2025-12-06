<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LogTypeCreator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:logtype';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Saves Log type so that it can be used in the application';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTypes = [
            ['slug'=>'User Login', 'message'=>'User has logged in '],
        ];
        foreach ($logTypes as $logType){

        }
        return 0;
    }
}
