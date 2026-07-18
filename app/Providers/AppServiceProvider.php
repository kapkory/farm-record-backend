<?php

namespace App\Providers;

use App\Models\Core\Animal;
use App\Models\Core\AnimalEvent;
use App\Models\Core\AnimalGroup;
use App\Models\Core\Farm;
use App\Models\Core\Hive;
use App\Models\Core\Planting;
use App\Models\Core\Treatment;
use App\Observers\AnimalEventObserver;
use App\Observers\HiveObserver;
use App\Observers\PlantingObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Relation::morphMap([
            'planting' => Planting::class,
            'animal_group' => AnimalGroup::class,
            'animal' => Animal::class,
            'farm' => Farm::class,
            'treatment' => Treatment::class,
            'hive' => Hive::class,
        ]);

        Planting::observe(PlantingObserver::class);
        AnimalEvent::observe(AnimalEventObserver::class);
        Hive::observe(HiveObserver::class);
    }
}
