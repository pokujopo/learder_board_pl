<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
            Gate::define('admin', function ($user) {
                return $user->role === 'admin';
            });

            Gate::define('user', function ($user) {
                return $user->role === 'user';
            });
            ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return 'http://localhost:5174/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
        }
}
