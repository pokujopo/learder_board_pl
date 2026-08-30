<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use App\Services\Ranking\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\Game;

class FetchApi extends Controller
{
    private const CACHE_DURATION = 60;

    public function __construct(
        private ReferralService $referralService,
        private RankingService $rankingService
    ) {
    }

    /**
     * Internal endpoint used to synchronize a referral user.
     *
     * Required:
     * - game_id
     * - refercode
     */
    public function fetch_api(Request $request)
    {
        $validated = $request->validate([
            'game_id' => [
                'required',
                'integer',
                'exists:games,id',
            ],

            'refercode' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
        ]);

        try {
            $gameId = (int) $validated['game_id'];
            $refercode = $validated['refercode'];

            /*
            |--------------------------------------------------------------------------
            | Find the user's verified registration for this game
            |--------------------------------------------------------------------------
            */

            $registration = GameUser::query()
                ->where('user_id', $request->user()?->id)
                ->where('game_id', $gameId)
                ->where('refercode', $refercode)
                ->where('refercode_verified', true)
                ->with('game')
                ->first();

            if (!$registration || !$registration->game) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Verified game registration not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Ask external API to identify the referral
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | We do NOT determine company from the refercode ourselves.
            | The external API response is the source of truth.
            |
            */

            $result = $this->referralService->fetchAndSync(
                $refercode,
                $registration->game
            );

            /*
            |--------------------------------------------------------------------------
            | Ranking cache
            |--------------------------------------------------------------------------
            */

            if ($result['hasChanges']) {
                Cache::forget(
                    "game:{$gameId}:ranking"
                );
            }

            return response()->json([
                'status' => 200,

                'message' => $result['hasChanges']
                    ? 'User data updated'
                    : 'User data synchronized',

                'game_id' => $gameId,

                'data' => $result['user'],

                'hasChanges' => $result['hasChanges'],

                'changes' => $result['changes'],
            ]);

        } catch (Throwable $e) {

            Log::error('Referral synchronization failed.', [
                'game_id' => $request->input('game_id'),
                'refercode' => $request->input('refercode'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Unable to synchronize referral data.',
            ], 500);
        }
    }

    /**
     * Get ranking for a specific game.
     */
    public function ranking_api(
                Request $request,
                Game $game
            ) {
                $perPage = min(
                    $request->integer('per_page', 50),
                    100
                );

                $page = $request->integer('page', 1);

                $cacheKey = "game:{$game->id}:ranking:page:{$page}:per_page:{$perPage}";

                $cached = Cache::get($cacheKey);

                if ($cached !== null) {

                    $ranking = collect($cached['data']);

                    $currentUser = $ranking->firstWhere(
                        'user_id',
                        $request->user()->id
                    );

                    return response()->json([
                        'status' => 200,
                        'message' => 'Rankings retrieved from cache',
                        'cached' => true,
                        'game_id' => $game->id,
                        'current_user' => $currentUser,
                        'pagination' => $cached['pagination'],
                        'data' => $ranking,
                    ]);
                }

                $ranking = $this->rankingService
                    ->updateRanks($game->id);

                $paginated = $ranking->forPage($page, $perPage);

                $data = $paginated
                    ->map(function (GameUser $gameUser) {

                        return [
                            'rank' => $gameUser->current_rank,

                            'user_id' => $gameUser->user_id,

                            'refercode' => $gameUser->refercode,

                            'name' =>
                                $gameUser->yasuser->compitetor_name,

                            'total_inviter_number' =>
                                $gameUser->yasuser->total_inviter_number,

                            'previous_rank' =>
                                $gameUser->previous_rank,

                            'rank_change' =>
                                $gameUser->rank_change,

                            'rank_movement' =>
                                $gameUser->rank_movement,
                        ];
                    })
                    ->values()
                    ->all();

                $pagination = [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $ranking->count(),
                    'last_page' => (int) ceil(
                        $ranking->count() / $perPage
                    ),
                ];

                $cacheData = [
                    'data' => $data,
                    'pagination' => $pagination,
                ];

                Cache::put(
                    $cacheKey,
                    $cacheData,
                    self::CACHE_DURATION
                );

                $currentUser = collect($data)->firstWhere(
                    'user_id',
                    $request->user()->id
                );

                return response()->json([
                    'status' => 200,
                    'message' => 'Rankings retrieved successfully',
                    'cached' => false,
                    'game_id' => $game->id,
                    'current_user' => $currentUser,
                    'pagination' => $pagination,
                    'data' => $data,
                ]);
            }
}