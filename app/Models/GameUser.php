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

        // External referral data
        'customer_name',
        'invitor_number',
        'last_synced_at',
        'status',

        // Ranking
        'current_rank',
        'previous_rank',
        'rank_change',
        'rank_movement',
    ];

    protected $casts = [
        'refercode_verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_synced_at' => 'datetime',

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

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}