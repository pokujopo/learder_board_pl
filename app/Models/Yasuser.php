<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Yasuser extends Model
{
    protected $table = 'yasuser';

    protected $fillable = [
        'refercode',
        'compitetor_name',
        'total_inviter_number',
        'last_synced_at',
    ];

    protected $casts = [
        'total_inviter_number' => 'integer',
        'last_synced_at' => 'datetime',
    ];
}