<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use Illuminate\Http\Request;
class UserController extends Controller {
 public function me(Request $r){$u=$r->user();return response()->json(['status'=>200,'data'=>['user'=>['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone_number,'location'=>$u->location,'role'=>$u->role,'email_verified_at'=>$u->email_verified_at]]]);}
 public function update(Request $r){$u=$r->user();$v=$r->validate(['name'=>'sometimes|string|max:255','phone'=>'sometimes|string|max:20|unique:users,phone_number,'.$u->id,'location'=>'sometimes|nullable|string|max:255']);$u->update(array_filter(['name'=>$v['name']??null,'phone_number'=>$v['phone']??null,'location'=>$v['location']??null],fn($x)=>$x!==null));return $this->me($r);}
 public function stats(Request $r){$u=$r->user();$parts=$u->gameUsers()->where('refercode_verified',true);return response()->json(['status'=>200,'data'=>['stats'=>['competitions_joined'=>(clone $parts)->count(),'current_rankings'=>(clone $parts)->whereNotNull('current_rank')->count()]]]);}
}
