<?php
namespace App\Jobs;

use App\Models\Yasuser;
use App\Services\Referral\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;
use App\Models\GameUser;

class SyncReferralUser implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Maximum execution time for this job.
     */
    public int $timeout = 45;

    /**
     * Retry delays in seconds.
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
    public int $gameUserId
) {
}

    public function handle(
            ReferralService $referralService
        ): void {
            $gameUser = GameUser::with('game')
                ->find($this->gameUserId);

            if (!$gameUser) {
                return;
            }

            if (!$gameUser->refercode_verified) {
                return;
            }

            if (!$gameUser->game) {
                return;
            }

            $result = $referralService->fetchAndSync(
                $gameUser->refercode,
                $gameUser->game
            );

            if ($result['hasChanges']) {
                cache()->forget(
                    'game:' . $gameUser->game_id . ':ranking'
                );
            }
        }

    /**
     * Handle a job that has failed permanently.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Referral synchronization permanently failed', [
            'user_id' => $this->gameUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}

/*
<?php

namespace App\Jobs;

use App\Services\Referral\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncReferralUser implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId
    ) {
    }

    public function handle(ReferralService $referralService): void
    {
        $user = \App\Models\Yasuser::find($this->userId);

        if (!$user) {
            return;
        }

        try {
            $result = $referralService->fetchAndSync(
                $user->refercode
            );

            if ($result['hasChanges']) {
                Log::info('Referral data updated', [
                    'refercode' => $user->refercode,
                    'changes' => $result['changes'],
                ]);
            }

        } catch (Throwable $e) {

            Log::error('Referral synchronization failed', [
                'user_id' => $this->userId,
                'refercode' => $user->refercode,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

*/