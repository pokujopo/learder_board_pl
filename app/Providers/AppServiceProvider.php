<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
//use Illuminate\Support\Facades\URL;

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
           /* if (app()->environment('production') || config('app.url') !== 'http://localhost') {
            URL::forceScheme('https');
                }*/
            Gate::define('admin', function ($user) {
                return $user->role === 'admin';
            });

            Gate::define('user', function ($user) {
                return $user->role === 'user';
            });
        }
}
