<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Mockery\Generator\StringManipulation\Pass\Pass;

class CustomServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // should be able to make dates to be immutable throughout the site
        Date::use(CarbonImmutable::class);

        if (!$this->app->isProduction()){
          Password::defaults(
              fn(): Password => Password::min(8)->max(255)->uncompromised()
            );
        }

        // prohibit destructive database commands in production
        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );
    }
}
