<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\GameUser;
use Illuminate\Database\Eloquent\Relations\HasOne;
//use App\Models\UserSetting;

#[Fillable(['name', 'email', 'password', 'phone_number', 'location', 'login_otp_verified_at',])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'login_otp_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
    public function games(): BelongsToMany
{
    return $this->belongsToMany(Game::class)
        ->withPivot([
            'refercode',
            'refercode_verified',
            'verified_at',
        ])
        ->withTimestamps();
}

   
public function gameUsers(): HasMany
    {
        return $this->hasMany(GameUser::class, 'user_id');
    }
public function settings(): HasOne
{
    return $this->hasOne(UserSetting::class);
}
    
}