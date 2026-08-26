<?php

namespace App\Services\Game;

use App\Models\Game;
use App\Models\GameUser;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;

class GameRegistrationService
{
    public function __construct(
        private ReferralService $referralService
    ) {
    }

    public function verifyAndRegister(
        int $userId,
        int $gameId,
        string $refercode
    ): array {
        return DB::transaction(function () use (
            $userId,
            $gameId,
            $refercode
        ) {

            /*
             * 1. Hakikisha game ipo.
             */
            $game = Game::findOrFail($gameId);

            /*
             * 2. Angalia kama USER HUYU tayari
             *    amesajiliwa kwenye game hii.
             *
             * Hii inazuia registration duplicate
             * kwa user + game.
             */
            $existingRegistration = GameUser::query()
                ->where('user_id', $userId)
                ->where('game_id', $gameId)
                ->first();

            if ($existingRegistration) {

                /*
                 * Kama refercode ni ileile,
                 * hii ni repeat request ya user yuleyule.
                 */
                if ($existingRegistration->refercode === $refercode) {

                    $yasUser = \App\Models\Yasuser::query()
                        ->where('refercode', $refercode)
                        ->first();

                    return [
                        'game' => $game,
                        'user' => $yasUser,
                        'registration' => $existingRegistration,
                        'hasChanges' => false,
                        'changes' => [],
                        'already_registered' => true,
                    ];
                }

                /*
                 * User huyu tayari yupo kwenye game hii
                 * lakini anajaribu kutumia refercode nyingine.
                 */
                return [
                    'status' => 409,
                    'message' =>
                        'You are already registered for this game.',
                ];
            }

            /*
             * 3. Muhimu sana:
             *
             * Refercode lazima iwe imetumika mara moja tu
             * ndani ya game hii.
             *
             * Hatujui company kwa kuangalia refercode.
             * External API ndiyo inathibitisha.
             */
            $refercodeAlreadyUsed = GameUser::query()
                ->where('game_id', $gameId)
                ->where('refercode', $refercode)
                ->exists();

            if ($refercodeAlreadyUsed) {
                return [
                    'status' => 409,
                    'message' =>
                        'This refercode has already been used in this game.',
                ];
            }

            /*
             * 4. Sasa ndipo tunapiga external API.
             *
             * External API ndiyo source ya taarifa
             * za refercode/user/company.
             */
            $result = $this->referralService
                ->fetchAndSync($refercode, $game);

            $yasUser = $result['user'];

            /*
             * 5. Register user kwenye game.
             *
             * Database UNIQUE constraint ya
             * game_id + refercode ndiyo final protection.
             */
            try {

                $gameUser = GameUser::create([
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'refercode' => $yasUser->refercode,
                    'refercode_verified' => true,
                    'verified_at' => now(),
                ]);

            } catch (UniqueConstraintViolationException $e) {

                /*
                 * Race condition:
                 * User wawili wakijaribu refercode moja
                 * kwa wakati mmoja, database itaamua mmoja tu.
                 */
                return [
                    'status' => 409,
                    'message' =>
                        'This refercode has already been used in this game.',
                ];
            }

            return [
                'status' => 200,
                'game' => $game,
                'user' => $yasUser,
                'registration' => $gameUser,
                'hasChanges' => $result['hasChanges'],
                'changes' => $result['changes'],
                'already_registered' => false,
            ];
        });
    }
}