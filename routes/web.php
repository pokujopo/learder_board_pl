<?php

use App\Http\Controllers\AffiliateRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/ref/{code}', AffiliateRedirectController::class)
    ->name('affiliate.redirect');