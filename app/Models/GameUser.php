<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameUser extends Model
{
    protected $table = 'game_user';

    protected $fillable = [
        'user_id',
        'game_id',
        'refercode',
        'refercode_verified',
        'verified_at',
        'current_rank',
        'previous_rank',
        'rank_change',
        'rank_movement',
    ];

    protected $casts = [
        'refercode_verified' => 'boolean',
        'verified_at' => 'datetime',
        'current_rank' => 'integer',
        'previous_rank' => 'integer',
        'rank_change' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Fetch the referral belonging to this exact competition + refercode.
     *
     * There is intentionally no Eloquent relationship here because the
     * referral is identified by two columns (game_id + refercode).
     */
    public function referral(): ?Yasuser
    {
        return Yasuser::query()
            ->where('game_id', $this->game_id)
            ->where('refercode', $this->refercode)
            ->first();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
