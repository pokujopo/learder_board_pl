<?php

use App\Http\Controllers\Api\Auth\register_login_api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FetchApi;
use App\Http\Controllers\Api\GameReferralController;
use App\Http\Controllers\Api\GameRegistrationController;
use Illuminate\Routing\Controller;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminGameController;
use App\Http\Controllers\Api\GameController;
use App\Http\Middleware\RateLimitMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Apply rate limiting to all API routes
Route::middleware([RateLimitMiddleware::class])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Auth Routes (No Authentication Required)
    |--------------------------------------------------------------------------
    */
    Route::post('/register', [register_login_api::class, 'register_api']);
    Route::post('/login', [register_login_api::class, 'login_api'])->name('login');
    Route::get('/games', [GameController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Requires Admin Role)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

        Route::get('/user/dashboard', function () {
            return response()->json([
                'status' => 200,
                'message' => 'Welcome admin',
            ]);
        });

        Route::post('/admin/games', [AdminGameController::class, 'store']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Routes (Requires Authentication)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Get authenticated user profile
        Route::get('profile/data/user', function (Request $request) {
            return response()->json([
                'status' => 200,
                'user' => $request->user(),
            ]);
        });

        // Get user dashboard with games joined
        Route::get('/profile/dashboard/user', [ProfileController::class, 'data']);

        // Logout
        Route::post('/logout', [register_login_api::class, 'logout_api']);

        // Game participation
        Route::post('games/{game}/verify-refercode', [GameRegistrationController::class, 'verifyRefercode']);

        // Leaderboard rankings
        Route::get('ranking/games/{game}', [FetchApi::class, 'ranking_api']);
    });
});

/*
|--------------------------------------------------------------------------
| Test Routes (Development Only)
|--------------------------------------------------------------------------
*/
Route::post('/yas/user/{refercode}', function ($refercode) {

    $all_customer = [
        "abc823" => [
            "refer_code" => "abc823",
            "customer_name" => "john doe",
            "invitor_number" => 30000,
        ],

        "abc120" => [
            "refer_code" => "abc120",
            "customer_name" => "jo de",
            "invitor_number" => 98000000000,
        ],

        "abc999" => [
            "refer_code" => "abc999",
            "customer_name" => "Test User",
            "invitor_number" => 2340000000,
        ],

        "abc270" => [
            "refer_code" => "abc270",
            "customer_name" => "Te User",
            "invitor_number" => 200000000000,
        ],
    ];

    if (!isset($all_customer[$refercode])) {
        return response()->json([
            "status" => 404,
            "message" => "Refercode not found",
        ], 404);
    }

    return response()->json([
        "status" => 200,
        "customer_all" => $all_customer[$refercode],
    ], 200);
});
