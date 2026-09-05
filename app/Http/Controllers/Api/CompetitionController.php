<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use App\Models\IdempotencyKey;
use App\Services\Referral\ReferralService;
use App\Services\Ranking\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class CompetitionController extends Controller
{
    public function __construct(
        private ReferralService $referral, private RankingService $ranking) {}
    public function index(Request $r) { $q=Game::query()->whereNotNull('start_date')->whereNotNull('end_date'); if($r->boolean('active_only',false)) $q->where('is_active',true); $games=$q->orderBy('start_date')->paginate(min((int)$r->input('per_page',20),100)); return response()->json(['status'=>200,'data'=>$games->through(fn($g)=>$this->resource($g))]); }
    public function show(Game $game) { return response()->json(['status'=>200,'data'=>['competition'=>$this->resource($game)]]); }
    public function join(Request $r, Game $game) {
        $v=$r->validate([
            'phone'=>'required|string|max:20',
            'referral_code'=>'required|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'consents'=>'required|array',
            'consents.terms'=>'required|accepted',
            'consents.sms'=>'sometimes|boolean',
            'consents.future_competitions'=>'sometimes|boolean'
            ]);
        $key=$r->header('Idempotency-Key'); 
        if(!$key || strlen($key)>128) 
            return response()->json([
                    'status'=>400,
                    'message'=>'Idempotency-Key header is required.
                    '],400);
        $existingKey=IdempotencyKey::where('user_id',$r->user()->id)->where('key',$key)->first(); 
        if($existingKey && $existingKey->response_body) 
            return response()->json($existingKey->response_body,$existingKey->response_status ?? 200);
        $game->refresh(); 
        $now=now(); 
        if(!$game->is_active || !$game->start_date || !$game->end_date || $now->lt($game->start_date) || $now->gt($game->end_date)) 
            return response()->json([
            'status'=>409,
            'message'=>'This competition is not currently accepting joins.'
                ],409);
        if($this->normalizePhone($v['phone']) !== $this->normalizePhone($r->user()->phone_number ?? '')) 
            return response()->json([
                'status'=>422,
                'message'=>'The phone number must match your account phone number.'
                    ],422);
        if(GameUser::where('user_id',$r->user()->id)->where('game_id',$game->id)->where('refercode_verified',true)->exists()) 
            return response()->json([
            'status'=>409,
            'message'=>'You are already registered for this competition.'
                ],409);
        if(GameUser::where('game_id',$game->id)->where('refercode',$v['referral_code'])->where('refercode_verified',true)->exists()) 
            return response()->json([
                'status'=>409,
                'message'=>'This referral code has already been used.'
                ],409);
        try { $result=$this->referral->fetchAndSync($v['referral_code'],$game); $yas=$result['user']; $registration=DB::transaction(function() use($r,$game,$yas,$v){ if(GameUser::where('game_id',$game->id)->where('refercode',$yas->refercode)->where('refercode_verified',true)->lockForUpdate()->exists()) throw new \RuntimeException('refercode_taken'); return GameUser::updateOrCreate(['user_id'=>$r->user()->id,'game_id'=>$game->id],['refercode'=>$yas->refercode,'refercode_verified'=>true,'verified_at'=>now()]); });
            $body=['status'=>201,'message'=>'Competition joined successfully.','data'=>['competition'=>$this->resource($game),'participation'=>['id'=>$registration->id,'referral_code'=>$registration->refercode,'verified'=>true,'joined_at'=>$registration->created_at]]];
            IdempotencyKey::updateOrCreate(['user_id'=>$r->user()->id,'key'=>$key],['endpoint'=>$r->path(),'response_status'=>201,'response_body'=>$body]); return response()->json($body,201);
        } catch(UniqueConstraintViolationException|\RuntimeException $e) { if($e instanceof \RuntimeException && $e->getMessage()!=='refercode_taken') report($e); return response()->json(['status'=>409,'message'=>'This referral code has already been used or the participation already exists.'],409); }
        catch(Throwable $e){ report($e); return response()->json(['status'=>503,'message'=>'Unable to verify referral code at this time.'],503); }
    }
    public function me(Request $r, Game $game)
     { 
        $p=GameUser::where('user_id',$r->user()->id)->where('game_id',$game->id)->first(); 
        if(!$p) return response()->json(['status'=>404,'message'=>'You have not joined this competition.'],404); 
        return response()->json(['status'=>200,'data'=>['participation'=>$p]]); 
    }
    public function leaderboard(Game $game) { return response()->json(['status'=>200,'data'=>['competition'=>$this->resource($game),'leaderboard'=>$this->ranking->getRanking($game->id)->map(fn($p)=>$this->rankResource($p))->values()]]); }
    public function myLeaderboard(Request $r, Game $game) { $p=GameUser::where('user_id',$r->user()->id)->where('game_id',$game->id)->first(); if(!$p) return response()->json(['status'=>404,'message'=>'You have not joined this competition.'],404); return response()->json(['status'=>200,'data'=>['ranking'=>$this->rankResource($p)]]); }
    public function referral(Request $r, Game $game) { $p=GameUser::where('user_id',$r->user()->id)->where('game_id',$game->id)->first(); if(!$p) return response()->json(['status'=>404,'message'=>'You have not joined this competition.'],404); return response()->json(['status'=>200,'data'=>['referral_code'=>$p->refercode,'verified'=>(bool)$p->refercode_verified]]); }
    public function referrals(Request $r, Game $game) { $p=GameUser::where('user_id',$r->user()->id)->where('game_id',$game->id)->first(); if(!$p) return response()->json(['status'=>404,'message'=>'You have not joined this competition.'],404); $yas=$p->yasuser; return response()->json(['status'=>200,'data'=>['referrals'=>[['refer_code'=>$yas?->refercode,'name'=>$yas?->compitetor_name,'invitor_number'=>$yas?->total_inviter_number,'last_synced_at'=>$yas?->last_synced_at]]]]); }
    private function resource(Game $g): array { $now=now(); $status=(!$g->start_date || !$g->end_date)?'draft':($now->lt($g->start_date)?'upcoming':($now->gt($g->end_date)?'completed':(($now->diffInHours($g->end_date,false)<=24)?'ending_soon':'live'))); return ['id'=>$g->public_id,'name'=>$g->name,'code'=>$g->code,'status'=>$status,'is_active'=>(bool)$g->is_active,'start_at'=>$g->start_date?->toISOString(),'end_at'=>$g->end_date?->toISOString(),'participants'=>$g->users()->wherePivot('refercode_verified',true)->count(),'prizes'=>['first_place'=>$g->first_place_prize,'second_place'=>$g->second_place_prize,'third_place'=>$g->third_place_prize],'rules'=>$g->competition_rules,'winning_instructions'=>$g->winning_instructions]; }
    private function rankResource(GameUser $p): array { $y=$p->yasuser; return ['user_id'=>$p->user_id,'name'=>$y?->compitetor_name ?? $p->user?->name,'score'=>(int)($y?->total_inviter_number ?? 0),'current_rank'=>$p->current_rank,'previous_rank'=>$p->previous_rank,'rank_change'=>$p->rank_change,'rank_movement'=>$p->rank_movement]; }
    private function normalizePhone(string $phone): string { return preg_replace('/\D+/','',$phone) ?? ''; }
}
