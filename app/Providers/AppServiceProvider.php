<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
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
        App::setLocale('id');
        config(['app.locale' => 'id']);
        Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_indonesia.1252', 'id_ID', 'id');
    }
}
