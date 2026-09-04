<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Yasuser extends Model
{
    protected $table = 'yasuser';

    protected $fillable = [
        'game_id',
        'company_id',
        'refercode',
        'compitetor_name',
        'total_inviter_number',
        'last_synced_at','status',
    ];

    protected $casts = [
        'total_inviter_number' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}