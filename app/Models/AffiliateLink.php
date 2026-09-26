<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AffiliateLink extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }
    public function referrals(): HasMany
{
    return $this->hasMany(AffiliateReferral::class);
}
    public function rewards(): HasManyThrough
{
    return $this->hasManyThrough(
        AffiliateReward::class,
        AffiliateReferral::class,
        'affiliate_link_id',
        'affiliate_referral_id',
        'id',
        'id'
    );
}
}
