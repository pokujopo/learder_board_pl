<?php

namespace App\Services\Game;

use App\Exceptions\RefercodeAlreadyUsedException;
use App\Models\Game;
use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class GameRegistrationService
{
    public function __construct(
        private ReferralService $referralService,
    ) {
    }

    /**
     * Verify the referral code with the external service and register the user.
     *
     * The external API call intentionally happens before the DB transaction so
     * a slow third-party service does not keep a database transaction open.
     */
    public function verifyAndRegister(
        int $userId,
        Game $game,
        string $refercode,
    ): array {
        $refercode = ReferralService::normalizeRefercode($refercode);

        $existingRegistration = GameUser::query()
            ->where('user_id', $userId)
            ->where('game_id', $game->id)
            ->first();

        if ($existingRegistration?->refercode_verified) {
            throw new \DomainException('You are already registered for this competition.');
        }

        // Fast path. The database unique constraint remains the final authority.
        if (GameUser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $refercode)
            ->where('refercode_verified', true)
            ->exists()) {
            throw new RefercodeAlreadyUsedException(
                'This referral code has already been used in this competition.'
            );
        }

        $result = $this->referralService->fetchAndSync($refercode, $game);
        $yasuser = $result['user'];

        try {
            $registration = DB::transaction(function () use (
                $userId,
                $game,
                $refercode,
                $existingRegistration,
            ) {
                $taken = GameUser::query()
                    ->where('game_id', $game->id)
                    ->where('refercode', $refercode)
                    ->where('refercode_verified', true)
                    ->lockForUpdate()
                    ->exists();

                if ($taken) {
                    throw new RefercodeAlreadyUsedException(
                        'This referral code has already been used in this competition.'
                    );
                }

                if ($existingRegistration) {
                    $existingRegistration->update([
                        'refercode' => $refercode,
                        'refercode_verified' => true,
                        'verified_at' => now(),
                    ]);

                    return $existingRegistration->fresh();
                }

                return GameUser::create([
                    'user_id' => $userId,
                    'game_id' => $game->id,
                    'refercode' => $refercode,
                    'refercode_verified' => true,
                    'verified_at' => now(),
                ]);
            });
        } catch (RefercodeAlreadyUsedException $e) {
            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            throw new RefercodeAlreadyUsedException(
                'This referral code has already been used in this competition.',
                previous: $e
            );
        }

        return [
            'game' => $game->fresh(),
            'user' => $yasuser,
            'registration' => $registration,
            'hasChanges' => $result['hasChanges'],
            'changes' => $result['changes'],
        ];
    }
}
