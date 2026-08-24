<?php

use App\Http\Controllers\Api\Auth\register_login_api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FetchApi;
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

});

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
 */

//user requast 234acbd
Route::post('internal/user',[FetchApi::class, 'fetch_api']);
Route::get('ranking/yas/gift/', [FetchApi::class, 'ranking_api']);


//demo ex ternal api
Route::post('/yas/user/{refercode}', function ($refercode) {
    $all_customer = [
        "abc123"=> [
        "refer_code"=> "abc123",
        "customer_name"=> "john doe",
        "invitor_number"=> 57,
        ],
         "abc124"=> [
        "refer_code"=> "abc124",
        "customer_name"=> "john iso",
        "invitor_number"=> 7,
         ],
          "abc125"=> [
        "refer_code"=> "abc125",
        "customer_name"=> "john doe",
        "invitor_number"=> 57,
        ]
        ];
    return response() -> json([
        "status"=>200,
        "customer_all"=> $all_customer[$refercode],

    ]);
});
