<?php

namespace App\Jobs;

use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use App\Services\Ranking\RankingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncReferralUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $gameUserId
    ) {
    }

    public function handle(
    ReferralService $referralService,
    RankingService $rankingService
): void {

    $gameUser = GameUser::with('game')
        ->find($this->gameUserId);

    if (!$gameUser) {
        return;
    }

    if (!$gameUser->refercode_verified) {
        return;
    }

    if (!$gameUser->refercode) {
        return;
    }

    if (!$gameUser->game) {
        return;
    }

    try {

        $result = $referralService->fetchAndSyncGameUser(
            $gameUser
        );

        /*
         * Referral data changed.
         *
         * Ranking must immediately be recalculated.
         */
        if ($result['hasChanges']) {

            $rankingResult = $rankingService->recalculate(
                $gameUser->game
            );

            Log::info(
                'Referral synchronized and ranking recalculated',
                [
                    'game_user_id' => $gameUser->id,
                    'game_id' => $gameUser->game_id,
                    'refercode' => $gameUser->refercode,
                    'changes' => $result['changes'],
                    'ranking' => $rankingResult,
                ]
            );
        }

    } catch (Throwable $e) {

        Log::error(
            'Game user referral synchronization failed',
            [
                'game_user_id' => $gameUser->id,
                'game_id' => $gameUser->game_id,
                'refercode' => $gameUser->refercode,
                'error' => $e->getMessage(),
            ]
        );

        throw $e;
    }
}

    public function failed(Throwable $exception): void
    {
        Log::critical(
            'Game user referral synchronization permanently failed',
            [
                'game_user_id' => $this->gameUserId,
                'error' => $exception->getMessage(),
            ]
        );
    }
}