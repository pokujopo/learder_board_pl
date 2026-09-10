<?php

namespace App\Console\Commands;

use App\Jobs\SyncReferralUser;
use App\Models\GameUser;
use Illuminate\Console\Command;

class SyncReferrals extends Command
{
    protected $signature = 'referrals:sync';

    protected $description = 'Synchronize all registered competition users with external referral service';

    public function handle(): int
    {
        $this->info('Starting referral synchronization...');

        $count = 0;

        GameUser::query()
            ->where('refercode_verified', true)
            ->whereNotNull('refercode')
            ->select(['id', 'refercode', 'game_id'])
            ->chunkById(100, function ($gameUsers) use (&$count) {

                foreach ($gameUsers as $gameUser) {
                    SyncReferralUser::dispatch($gameUser->id);

                    $count++;
                }
            });

        $this->info(
            "Dispatched {$count} referral synchronization jobs."
        );

        return self::SUCCESS;
    }
}