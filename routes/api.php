<?php

use App\Http\Controllers\Api\Auth\register_login_api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FetchApi;
use App\Http\Controllers\Api\GameReferralController;
use App\Http\Controllers\Api\GameRegistrationController;
//use League\Uri\Http;
//use Illuminate\Support\Facades\Http;
//use  App\Models\Yasuser;


//auth
Route::post('/register',[register_login_api::class, 'register_api']);
Route::post('/login', [register_login_api::class, 'login_api'])->name('login');

Route::middleware('auth:sanctum')->group(function () {

    // Get authenticated user
    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => 200,
            'user' => $request->user(),
        ]);
    });
    
    Route::post('/logout', [register_login_api::class, 'logout_api']);
    Route::post('internal/user',[FetchApi::class, 'fetch_api']);
    Route::post(
        'games/{game}/verify-refercode',
        [GameReferralController::class, 'verify']
    );
    Route::post(
        'games/{game}/verify-refercode',
        [GameRegistrationController::class, 'verifyRefercode']
    );
    Route::get(
        'ranking/games/{game}',
        [FetchApi::class, 'ranking_api']
    );


});


 Route::get('ranking/yas/gift/', [FetchApi::class, 'ranking_api']);



/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
 */

//user requast 234acbd


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
            "invitor_number" => 23400000,
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

        "company" => [
            "id" => 1,
            "name" => "YAS",
        ],

        "customer_all" => $all_customer[$refercode],
    ]);
});