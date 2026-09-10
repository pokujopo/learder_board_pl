<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\GameReferralController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Models\GameUser;
use App\Http\Controllers\Api\CompetitionUserRegistrationController;
use App\Http\Controllers\Api\RankingController;

Route::prefix('v1')->middleware([RateLimitMiddleware::class])->group(function () {
   

    //Route::get('competitions',[CompetitionController::class,'index']);

      /*
        |--------------------------------------------------------------------------
        | Public Competition APIs
        |--------------------------------------------------------------------------
        */
        Route::post(
            'competitions/user/registration',
            [CompetitionUserRegistrationController::class, 'register']
        );

        Route::get(
            'competitions',
            [CompetitionController::class, 'index']
        );
        Route::get(
                'competitions/{game}/ranking',
                [RankingController::class, 'index']
            );
        /*
        |--------------------------------------------------------------------------
        | Public Refercode Verification
        |--------------------------------------------------------------------------
        |
        | User can verify a refercode before creating an account.
        |
        */

        Route::post(
            'competitions/{game}/verify-refercode',
            [GameReferralController::class, 'verify']
        );




    Route::prefix('auth')->group(function () {
        Route::post('register',[AuthController::class,'register']);
        Route::post('login',[AuthController::class,'login']);
        Route::post('refresh',[AuthController::class,'refresh']);
        Route::post('forgot-password',[AuthController::class,'forgotPassword']);
        Route::post('reset-password',[AuthController::class,'resetPassword']);
    });
    Route::middleware(JwtAuthMiddleware::class)->group(function () {
        Route::prefix('auth')->group(function(){
            Route::get('me',[AuthController::class,'me']);
            Route::post('logout',[AuthController::class,'logout']);
            Route::post('change-password',[AuthController::class,'changePassword']);
        });
         Route::get(
                'competitions/{game}/ranking/me',
                [RankingController::class, 'index']
            );
        Route::prefix('users')->group(function(){Route::get('me',[UserController::class,'me']);Route::patch('me',[UserController::class,'update']);Route::get('me/stats',[UserController::class,'stats']);});
        Route::get('dashboard',[DashboardController::class,'show']);
        Route::prefix('competisstions/{game}')->group(function(){
            Route::post('join',[CompetitionController::class,'join']);
            Route::post('verify-refercode',[GameReferralController::class,'verify']);
            // Legacy compatibility for older clients/documentation.
          //  Route::post('games/{game}/verify-refercode',[GameReferralController::class,'verify']);
            Route::get('me',[CompetitionController::class,'me']);
            Route::get('leaderboard',[CompetitionController::class,'leaderboard']);
            Route::get('leaderboard/me',[CompetitionController::class,'myLeaderboard']);
            Route::get('referral',[CompetitionController::class,'referral']);
            Route::get('referrals',[CompetitionController::class,'referrals']);
        });
        Route::prefix('rewards')->group(function(){Route::get('/',[RewardController::class,'index']);Route::get('balance',[RewardController::class,'balance']);Route::get('history',[RewardController::class,'history']);Route::get('{reward}',[RewardController::class,'show']);Route::post('{reward}/claim',[RewardController::class,'claim']);});

        Route::prefix('admin')->middleware([RoleMiddleware::class.':admin'])->group(function(){
            Route::get('dashboard',[AdminController::class,'dashboard']);
            Route::prefix('competitions')->group(function(){Route::get('/',[AdminController::class,'competitions']);Route::post('/',[AdminController::class,'storeCompetition']);Route::get('{game}',[AdminController::class,'showCompetition']);Route::patch('{game}',[AdminController::class,'updateCompetition']);Route::delete('{game}',[AdminController::class,'destroyCompetition']);});
            Route::get('participants',[AdminController::class,'participants']);
            Route::get('participants/{participant}',[AdminController::class,'participant']);
            Route::get('referrals',[AdminController::class,'referrals']);
            Route::get('referrals/{referral}',[AdminController::class,'referral']);
            Route::patch('referrals/{referral}/status',[AdminController::class,'referralStatus']);
            Route::get('rewards',[AdminController::class,'rewards']);
            Route::get('integrations',[AdminController::class,'integrations']);
            Route::post('integrations',[AdminController::class,'createIntegration']);
            Route::patch('integrations/{game}',[AdminController::class,'updateIntegration']);
            Route::delete('integrations/{game}',[AdminController::class,'deleteIntegration']);
        });
    });
});


Route::post('/yas/{refercode}', function ($refercode) {

    $all_customer = [
        "ABC823" => [
            "refer_code" => "ABC823",
            "customer_name" => "john doe",
            "invitor_number" => 100009000990,
        ],

        "ABC120" => [
            "refer_code" => "ABC120",
            "customer_name" => "jo de",
            "invitor_number" => 8233333333333333333333,
        ],

        "ABC999" => [
            "refer_code" => "ABC999",
            "customer_name" => "Test User",
            "invitor_number" => 73433465,
        ],

        "ABC270" => [
            "refer_code" => "ABC270",
            "customer_name" => "Te User",
            "invitor_number" => 2909876,
        ],
        "ABC83" => [
            "refer_code" => "ABC83",
            "customer_name" => "john doe",
            "invitor_number" => 3000,
        ],

        "ABC10" => [
            "refer_code" => "ABC10",
            "customer_name" => "jo de",
            "invitor_number" => 980,
        ],

        "ABC99" => [
            "refer_code" => "ABC99",
            "customer_name" => "Test User",
            "invitor_number" => 2000000000,
        ],

        "ABC20" => [
            "refer_code" => "ABC20",
            "customer_name" => "Te User",
            "invitor_number" => 200,
        ],

        "ABC12" => [
            "refer_code" => "ABC12",
            "customer_name" => "jo de",
            "invitor_number" => 9800000,
        ],

        "ABC130" => [
            "refer_code" => "ABC130",
            "customer_name" => "Test User",
            "invitor_number" => 23,
        ],

        "ABC278" => [
            "refer_code" => "ABC278",
            "customer_name" => "Te User",
            "invitor_number" => 200000000000,
        ],
        "ABC833" => [
            "refer_code" => "ABC833",
            "customer_name" => "john doe",
            "invitor_number" => 30000,
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