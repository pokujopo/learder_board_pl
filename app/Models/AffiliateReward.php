<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateReward extends Model
{
    protected $fillable = [
        'affiliate_referral_id',
        'user_id',
        'points',
        'reason',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function affiliateReferral(): BelongsTo
    {
        return $this->belongsTo(
            AffiliateReferral::class
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}