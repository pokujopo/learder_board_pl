<?php
namespace App\Services\Ranking;
use App\Models\GameUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
class RankingService
{
    public function getRanking(int $gameId, int $limit=30): Collection
    {
        return GameUser::query()
            ->where('game_user.game_id',$gameId)
            ->where('game_user.refercode_verified',true)
            ->leftJoin('yasuser',function($join){$join->on('yasuser.game_id','=','game_user.game_id')->on('yasuser.refercode','=','game_user.refercode');})
            ->select('game_user.*')
            ->orderByDesc(DB::raw('COALESCE(yasuser.total_inviter_number,0)'))
            ->orderBy('game_user.id')
            ->with(['yasuser','user'])
            ->limit(min($limit,100))
            ->get()
            ->values();
    }
    public function updateRanks(int $gameId): Collection
    {
        $users=$this->getRanking($gameId,10000);
        DB::transaction(function() use($users){$users->each(function(GameUser $g,int $i){$new=$i+1;$old=$g->current_rank;$movement=$old===null?'new':($new<$old?'up':($new>$old?'down':'same'));$g->update(['previous_rank'=>$old,'current_rank'=>$new,'rank_change'=>$old===null?0:abs($old-$new),'rank_movement'=>$movement]);});});
        return $this->getRanking($gameId,100);
    }
}
