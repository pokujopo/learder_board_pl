<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
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
use App\Http\Controllers\AffiliateReferralController;
use App\Models\GameUser;
use App\Http\Controllers\Api\CompetitionUserRegistrationController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\ExistingUserGameJoinController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\AffiliateRedirectController;
use App\Http\Controllers\AffiliateAnalyticsController;



Broadcast::routes([
    'prefix' => 'v1',
    'middleware' => [
        JwtAuthMiddleware::class,
    ],
]);

Route::get('/v1/welcome', function () {
    return response()->json([
        'v1' => 'api v1 created by PTZ'
    ]);
});


//Route::get('/ref/{code}', AffiliateRedirectController::class)->name('affiliate.redirect');

Route::get('/debug/client-ip', function (Request $request) {
    return response()->json([
        'ip' => $request->ip(),
        'xff' => $request->header('X-Forwarded-For'),
        'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
    ]);
});
Route::prefix('v1')->middleware([RateLimitMiddleware::class])->group(function () {

        Route::post(
            'competitions/user/registration',
            [CompetitionUserRegistrationController::class, 'register']
        );
        Route::post(
            'competitions/{game}/verify-refercode',
            [GameReferralController::class, 'verify']
        );
    Route::get(
            'competitions',
            [CompetitionController::class, 'index']
        );

        Route::get(
                'competitions/{game}/ranking',
                [RankingController::class, 'index']
            );

        
    Route::prefix('auth')->group(function () {
        Route::post('register',[AuthController::class,'register']);
        Route::post('login',[AuthController::class,'login']);
        Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
        Route::post('refresh',[AuthController::class,'refresh']);
        Route::post('forgot-password',[AuthController::class,'forgotPassword']);
        Route::post('reset-password',[AuthController::class,'resetPassword']);
       
    });

    Route::middleware(JwtAuthMiddleware::class)->group(function () {
         Route::get(
        '/competitions/{game}/analytics',
        [AnalyticsController::class, 'show']
    );
        Route::get(
                'competitions/{game}/ranking/me',
                [RankingController::class, 'index']
            );
        Route::post('/affiliate/referrals/verify', AffiliateReferralController::class);

            Route::post(
                '/competitions/{game}/join',
                [ExistingUserGameJoinController::class, 'store']
            );
        Route::get('/affiliate/analytics', AffiliateAnalyticsController::class);

        Route::prefix('auth')->group(function(){

            Route::get('me',[AuthController::class,'me']);
            Route::post('logout',[AuthController::class,'logout']);
            Route::post('change-password',[AuthController::class,'changePassword']);
            Route::post('change-password/verify-otp',[AuthController::class,'verifyChangePasswordOtp']);

        });


        Route::prefix('settings')->group(function () {

            Route::get('/', [
                SettingsController::class,
                'show'
            ]);

            Route::patch('/profile', [
                SettingsController::class,
                'updateProfile'
            ]);

            Route::patch('/payout', [
                SettingsController::class,
                'updatePayout'
            ]);

            Route::patch('/notifications', [
                SettingsController::class,
                'updateNotifications'
            ]);

        });

        Route::prefix('admin')->middleware([RoleMiddleware::class.':admin'])->group(function(){

            Route::get('dashboard',[AdminController::class,'dashboard']);
            Route::prefix('competitions')->group(function(){
                Route::post('/',[AdminController::class,'storeCompetition']);
            });
            
        });
    });
});


Route::post('/yas/{refercode}', function ($refercode) {

    $all_customer = [
        "ABC823" => [
            "refer_code" => "ABC823",
            "customer_name" => "john doe",
            "invitor_number" => 70,
        ],
        "ABC824" => [
            "refer_code" => "ABC824",
            "customer_name" => "NEW doe",
            "invitor_number" => 100,
        ],

        "ABC809" => [
            "refer_code" => "ABC809",
            "customer_name" => "NEW doe",
            "invitor_number" => 100,
        ],

        "ABC825" => [
            "refer_code" => "ABC825",
            "customer_name" => "NEoe",
            "invitor_number" => 999,
        ],

        "ABC826" => [
            "refer_code" => "ABC826",
            "customer_name" => "NEoe",
            "invitor_number" => 999,
        ],

        "ABC827" => [
            "refer_code" => "ABC827",
            "customer_name" => "NEoe",
            "invitor_number" => 999,
        ],



        "ABC120" => [
            "refer_code" => "ABC120",
            "customer_name" => "jo de",
            "invitor_number" => 89,
        ],

        "ABC999" => [
            "refer_code" => "ABC999",
            "customer_name" => "Test User",
            "invitor_number" => 7,
        ],

        "ABC270" => [
            "refer_code" => "ABC270",
            "customer_name" => "Te User",
            "invitor_number" => 92,
        ],
        "ABC83" => [
            "refer_code" => "ABC83",
            "customer_name" => "john doe",
            "invitor_number" => 40,
        ],

        "ABC10" => [
            "refer_code" => "ABC10",
            "customer_name" => "jo de",
            "invitor_number" => 70,
        ],

        "ABC99" => [
            "refer_code" => "ABC99",
            "customer_name" => "Test User",
            "invitor_number" => 20,
        ],

        "ABC20" => [
            "refer_code" => "ABC20",
            "customer_name" => "Te User",
            "invitor_number" => 5000,
        ],

        "ABC12" => [
            "refer_code" => "ABC12",
            "customer_name" => "jo de",
            "invitor_number" => 9,
        ],

        "ABC130" => [
            "refer_code" => "ABC130",
            "customer_name" => "Test User",
            "invitor_number" => 4333,
        ],

        "ABC278" => [
            "refer_code" => "ABC278",
            "customer_name" => "Te User",
            "invitor_number" => 8890,
        ],
        "ABC833" => [
            "refer_code" => "ABC833",
            "customer_name" => "john doe",
            "invitor_number" => 9,
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
