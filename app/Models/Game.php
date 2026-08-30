<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;


class Game extends Model
{
    protected $fillable = [
            'name',
            'code',
            'is_active',
            'external_api_base_url',
            'public_id',

            'start_date',
            'end_date',

            'first_place_prize',
            'second_place_prize',
            'third_place_prize',

            'competition_rules',
            'winning_instructions',
        ];

    protected $casts = [
        'is_active' => 'boolean',

        'start_date' => 'datetime',
        'end_date' => 'datetime',

        'first_place_prize' => 'decimal:2',
        'second_place_prize' => 'decimal:2',
        'third_place_prize' => 'decimal:2',
    ];

    public function yasusers(): HasMany
    {
        return $this->hasMany(Yasuser::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function users(): BelongsToMany
            {
                return $this->belongsToMany(User::class)
                    ->withPivot([
                        'refercode',
                        'refercode_verified',
                        'verified_at',
                    ])
                    ->withTimestamps();
            }
    protected static function booted(): void
                {
                    static::creating(function (Game $game) {
                        $game->public_id = 'gm_' . Str::random(24);
                    });
                }

                public function getRouteKeyName(): string
                {
                    return 'public_id';
                }

}

