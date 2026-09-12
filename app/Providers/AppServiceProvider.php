<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Notifications\ResetPassword;


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
            return 'https://pawacode.com/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
        }
}
