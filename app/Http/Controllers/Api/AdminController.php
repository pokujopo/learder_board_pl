<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use App\Models\Reward;
use App\Models\Yasuser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
 public function dashboard(){return response()->json(['status'=>200,'data'=>['metrics'=>['users'=>DB::table('users')->count(),'competitions'=>Game::count(),'participants'=>GameUser::where('refercode_verified',true)->count(),'rewards'=>Reward::sum('amount')]]]);}
 public function competitions(Request $r){return response()->json(['status'=>200,'data'=>['competitions'=>Game::latest()->paginate(min((int)$r->input('per_page',20),100))]]);}
 public function storeCompetition(Request $r){$v=$r->validate(['name'=>'required|string|max:255','code'=>'required|string|max:100|alpha_dash|unique:games,code','is_active'=>'sometimes|boolean','external_api_base_url'=>'required|url|max:2048','start_at'=>'required|date','end_at'=>'required|date|after:start_at','first_prize'=>'required|numeric|min:0','second_prize'=>'required|numeric|min:0','third_prize'=>'required|numeric|min:0','competition_rules'=>'required|string','winning_instructions'=>'required|string']);$g=Game::create(['name'=>$v['name'],'code'=>$v['code'],'is_active'=>$v['is_active']??true,'external_api_base_url'=>$v['external_api_base_url'],'start_date'=>$v['start_at'],'end_date'=>$v['end_at'],'first_place_prize'=>$v['first_prize'],'second_place_prize'=>$v['second_prize'],'third_place_prize'=>$v['third_prize'],'competition_rules'=>$v['competition_rules'],'winning_instructions'=>$v['winning_instructions']]);return response()->json(['status'=>201,'message'=>'Competition created successfully.','data'=>['competition'=>$g]],201);}
 public function showCompetition(Game $game){return response()->json(['status'=>200,'data'=>['competition'=>$game]]);}
 public function updateCompetition(Request $r,Game $game){$v=$r->validate(['name'=>'sometimes|string|max:255','code'=>'sometimes|string|max:100|alpha_dash|unique:games,code,'.$game->id,'is_active'=>'sometimes|boolean','external_api_base_url'=>'sometimes|url|max:2048','start_at'=>'sometimes|date','end_at'=>'sometimes|date|after:start_at','first_prize'=>'sometimes|numeric|min:0','second_prize'=>'sometimes|numeric|min:0','third_prize'=>'sometimes|numeric|min:0','competition_rules'=>'sometimes|string','winning_instructions'=>'sometimes|string']);$map=['name'=>'name','code'=>'code','is_active'=>'is_active','external_api_base_url'=>'external_api_base_url','start_at'=>'start_date','end_at'=>'end_date','first_prize'=>'first_place_prize','second_prize'=>'second_place_prize','third_prize'=>'third_place_prize','competition_rules'=>'competition_rules','winning_instructions'=>'winning_instructions'];foreach($map as $from=>$to)if(array_key_exists($from,$v))$game->{$to}=$v[$from];$game->save();return response()->json(['status'=>200,'message'=>'Competition updated successfully.','data'=>['competition'=>$game]]);}
 public function destroyCompetition(Game $game){$game->update(['is_active'=>false]);return response()->json(['status'=>200,'message'=>'Competition deactivated successfully.']);}
 public function participants(Request $r){$q=GameUser::with(['user','game','yasuser'])->where('refercode_verified',true);if($r->filled('competition_id'))$q->whereHas('game',fn($x)=>$x->where('public_id',$r->competition_id));return response()->json(['status'=>200,'data'=>$q->latest()->paginate(min((int)$r->input('per_page',20),100))]);}
 public function participant(GameUser $participant){$participant->load(['user','game','yasuser']);return response()->json(['status'=>200,'data'=>['participant'=>$participant]]);}
 public function referrals(Request $r){$q=Yasuser::with('game');if($r->filled('competition_id'))$q->whereHas('game',fn($x)=>$x->where('public_id',$r->competition_id));return response()->json(['status'=>200,'data'=>$q->latest('last_synced_at')->paginate(min((int)$r->input('per_page',20),100))]);}
 public function referral(Yasuser $referral){return response()->json(['status'=>200,'data'=>['referral'=>$referral->load('game')]]);}
 public function referralStatus(Request $r,Yasuser $referral){$v=$r->validate(['status'=>'required|string|in:active,blocked,verified']);$referral->update(['status'=>$v['status']]);return response()->json(['status'=>200,'message'=>'Referral status updated.','data'=>['referral'=>$referral]]);}
 public function rewards(Request $r){$q=Reward::with(['user']);return response()->json(['status'=>200,'data'=>$q->latest()->paginate(20)]);}
 public function integrations(){return response()->json(['status'=>200,'data'=>['integrations'=>Game::select('public_id','name','external_api_base_url','is_active')->get()]]);}
 public function createIntegration(Request $r){$v=$r->validate(['competition_id'=>'required|exists:games,public_id','external_api_base_url'=>'required|url|max:2048']);$g=Game::where('public_id',$v['competition_id'])->firstOrFail();$g->update(['external_api_base_url'=>$v['external_api_base_url']]);return response()->json(['status'=>201,'message'=>'Integration created/updated successfully.','data'=>['integration'=>$g]],201);}
 public function updateIntegration(Request $r,Game $game){$v=$r->validate(['external_api_base_url'=>'required|url|max:2048','is_active'=>'sometimes|boolean']);$game->update($v);return response()->json(['status'=>200,'message'=>'Integration updated successfully.','data'=>['integration'=>$game]]);}
 public function deleteIntegration(Game $game){$game->update(['external_api_base_url'=>null]);return response()->json(['status'=>200,'message'=>'Integration removed successfully.']);}
}
