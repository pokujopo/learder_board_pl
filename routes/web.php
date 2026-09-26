<?php

use App\Http\Controllers\AffiliateRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/ref/{code}', AffiliateRedirectController::class)
    ->middleware(\App\Http\Middleware\RateLimitMiddleware::class)
    ->name('affiliate.redirect');