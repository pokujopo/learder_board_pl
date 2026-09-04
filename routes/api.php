<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Models\GameUser;

Route::prefix('v1')->middleware([RateLimitMiddleware::class])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register',[AuthController::class,'register']);
        Route::post('login',[AuthController::class,'login']);
        Route::post('refresh',[AuthController::class,'refresh']);
        Route::post('forgot-password',[AuthController::class,'forgotPassword']);
        Route::post('reset-password',[AuthController::class,'resetPassword']);
    });

    Route::get('competitions',[CompetitionController::class,'index']);
    Route::get('competitions/{game}',[CompetitionController::class,'show']);

    Route::middleware(JwtAuthMiddleware::class)->group(function () {
        Route::prefix('auth')->group(function(){
            Route::get('me',[AuthController::class,'me']);
            Route::post('logout',[AuthController::class,'logout']);
            Route::post('change-password',[AuthController::class,'changePassword']);
        });
        Route::prefix('users')->group(function(){Route::get('me',[UserController::class,'me']);Route::patch('me',[UserController::class,'update']);Route::get('me/stats',[UserController::class,'stats']);});
        Route::get('dashboard',[DashboardController::class,'show']);
        Route::prefix('competitions/{game}')->group(function(){
            Route::post('join',[CompetitionController::class,'join']);
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
