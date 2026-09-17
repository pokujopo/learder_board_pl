<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Game;
use App\Models\GameUser;

Broadcast::channel(
    'competition.{publicId}.ranking',
    function ($user, string $publicId) {

        $game = Game::where(
            'public_id',
            $publicId
        )->first();

        if (!$game) {
            return false;
        }

        return GameUser::where('game_id', $game->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
);