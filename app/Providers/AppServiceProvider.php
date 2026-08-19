<?php

namespace App\Providers;

use App\Models\Admin;
use App\Support\AdminAbilities;
use Illuminate\Support\Facades\Gate;
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
        Gate::define(
            AdminAbilities::VIEW_FAMILY_OVERVIEW,
            static fn (mixed $actor): bool => $actor instanceof Admin && $actor->exists
        );
    }
}
