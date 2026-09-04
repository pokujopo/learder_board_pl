<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Reward extends Model { protected $fillable=['user_id','game_id','amount','currency','status','claimed_at','metadata']; protected $casts=['amount'=>'decimal:2','claimed_at'=>'datetime','metadata'=>'array']; }
