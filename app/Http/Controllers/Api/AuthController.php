<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(private JwtService $jwt) {}
    public function register(Request $r) {
        $v=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|max:255|unique:users,email','phone'=>'required|string|max:20|unique:users,phone_number','location'=>'nullable|string|max:255','password'=>'required|string|min:8|confirmed']);
        $u=User::create(['name'=>$v['name'],'email'=>$v['email'],'phone_number'=>$v['phone'],'location'=>$v['location']??null,'password'=>$v['password'],'role'=>'user']);
        return $this->tokenResponse($u, 'User registered successfully.', 201);
    }
    public function login(Request $r) {
        $v=$r->validate(['email'=>'required|email','password'=>'required|string']); $u=User::where('email',$v['email'])->first();
        if(!$u || !Hash::check($v['password'],$u->password)) return response()->json(['status'=>401,'message'=>'Invalid email or password.'],401);
        return $this->tokenResponse($u, 'Login successful.');
    }
    public function refresh(Request $r) {
        $plain=$r->cookie('refresh_token');
        if(!$plain) return response()->json(['status'=>401,'message'=>'Refresh token is required.'],401);
        $rt=RefreshToken::where('token_hash',hash('sha256',$plain))->whereNull('revoked_at')->first();
        if(!$rt) return response()->json(['status'=>401,'message'=>'Invalid or expired refresh token.'],401);
        if($rt->revoked_at) { RefreshToken::where('user_id',$rt->user_id)->whereNull('revoked_at')->update(['revoked_at'=>now()]); return response()->json(['status'=>401,'message'=>'Refresh token reuse detected. Please sign in again.'],401); }
        if($rt->expires_at->isPast()) return response()->json(['status'=>401,'message'=>'Invalid or expired refresh token.'],401);
        $new=Str::random(96); $rt->update(['revoked_at'=>now(),'replaced_by'=>hash('sha256',$new)]); $u=$rt->user;
        $cookie=cookie('refresh_token',$new,(int)env('REFRESH_TOKEN_TTL_MINUTES',43200),'/','', (bool)env('COOKIE_SECURE',true),true,false,env('COOKIE_SAMESITE','lax'));
        return response()->json(['status'=>200,'message'=>'Token refreshed.','data'=>$this->accessTokenData($u)])->withCookie($cookie);
    }
    public function logout(Request $r) {
        $plain=$r->cookie('refresh_token'); if($plain) RefreshToken::where('token_hash',hash('sha256',$plain))->update(['revoked_at'=>now()]);
        $r->user()?->tokens()->delete();
        return response()->json(['status'=>200,'message'=>'Logout successful.'])->withCookie(cookie()->forget('refresh_token'));
    }
    public function me(Request $r) { return response()->json(['status'=>200,'data'=>['user'=>$this->user($r->user())]]); }
    public function forgotPassword(Request $r) { $v=$r->validate(['email'=>'required|email']); $status=Password::sendResetLink(['email'=>$v['email']]); return response()->json(['status'=>200,'message'=>'If the account exists, password reset instructions will be sent.']); }
    public function resetPassword(Request $r) { $v=$r->validate(['email'=>'required|email','token'=>'required|string','password'=>'required|string|min:8|confirmed']); $status=Password::reset(['email'=>$v['email'],'password'=>$v['password'],'password_confirmation'=>$v['password_confirmation'],'token'=>$v['token']],function(User $u,string $password){$u->forceFill(['password'=>$password])->save();RefreshToken::where('user_id',$u->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);}); if($status!==Password::PASSWORD_RESET) return response()->json(['status'=>422,'message'=>'Unable to reset password.'],422); return response()->json(['status'=>200,'message'=>'Password reset successfully.']); }
    public function changePassword(Request $r) { $v=$r->validate(['current_password'=>'required|string','password'=>'required|string|min:8|confirmed']); if(!Hash::check($v['current_password'],$r->user()->password)) return response()->json(['status'=>422,'message'=>'Current password is incorrect.'],422); $r->user()->update(['password'=>$v['password']]); RefreshToken::where('user_id',$r->user()->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]); return response()->json(['status'=>200,'message'=>'Password changed successfully.']); }
    private function accessTokenData(User $u): array { $permissions=$u->isAdmin()?['user:read','user:update','competition:read','competition:join','leaderboard:read','referral:read','reward:read','admin:dashboard','admin:competition','admin:participant','admin:referral','admin:integration']:['user:read','user:update','competition:read','competition:join','leaderboard:read','referral:read','reward:read']; return $this->jwt->issue($u,$permissions)+['token_type'=>'Bearer']; }
    private function tokenResponse(User $u, string $message, int $status=200) {
        $plain=Str::random(96);
        RefreshToken::where('user_id',$u->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        RefreshToken::create(['user_id'=>$u->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addMinutes((int)env('REFRESH_TOKEN_TTL_MINUTES',43200))]);
        $cookie=cookie('refresh_token',$plain,(int)env('REFRESH_TOKEN_TTL_MINUTES',43200),'/','',(bool)env('COOKIE_SECURE',true),true,false,env('COOKIE_SAMESITE','lax'));
        return response()->json(['status'=>$status,'message'=>$message,'data'=>['user'=>$this->user($u),'token'=>$this->accessTokenData($u)]],$status)->withCookie($cookie);
    }

    private function user(User $u): array { return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'phone'=>$u->phone_number,'location'=>$u->location,'role'=>$u->role,'email_verified_at'=>$u->email_verified_at]; }
}
