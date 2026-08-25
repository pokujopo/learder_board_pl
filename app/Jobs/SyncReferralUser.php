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