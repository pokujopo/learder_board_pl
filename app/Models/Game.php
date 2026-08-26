<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Game extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

}