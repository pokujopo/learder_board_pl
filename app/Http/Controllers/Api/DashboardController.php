<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use App\Models\GameUser;
class DashboardController extends Controller { public function show(Request $r){$u=$r->user();$p=$u->gameUsers()->with(['game','yasuser'])->where('refercode_verified',true)->latest()->get();return response()->json(['status'=>200,'data'=>['user'=>['id'=>$u->id,'name'=>$u->name,'email'=>$u->email],'competitions'=>$p->map(fn($x)=>['competition'=>['id'=>$x->game->public_id,'name'=>$x->game->name],'referral_code'=>$x->refercode,'score'=>(int)($x->yasuser?->total_inviter_number??0),'rank'=>$x->current_rank,'joined_at'=>$x->created_at])->values()]]);}}
