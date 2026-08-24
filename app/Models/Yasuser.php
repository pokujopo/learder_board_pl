<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Yasuser extends Model
{
    protected $table = 'yasuser';
    protected $fillable = ['refercode', 'compitetor_name', 'total_inviter_number'];
}
