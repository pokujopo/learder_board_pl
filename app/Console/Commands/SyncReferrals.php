<?php

namespace App\Console\Commands;

use App\Jobs\SyncReferralUser;
use App\Models\Yasuser;
use Illuminate\Console\Command;

class SyncReferrals extends Command
{
    protected $signature = 'referrals:sync';

    protected $description = 'Synchronize all registered referral users';

    public function handle(): int
    {
        $this->info('Starting referral synchronization...');

        $count = 0;

        Yasuser::query()
            ->select(['id', 'refercode'])
            ->chunkById(100, function ($users) use (&$count) {

                foreach ($users as $user) {
                    SyncReferralUser::dispatch($user->id);

                    $count++;
                }
            });

        $this->info(
            "Dispatched {$count} referral synchronization jobs."
        );

        return self::SUCCESS;
    }
}