<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use  App\Models\Yasuser;

class FetchApi extends Controller
{   
    public function fetch_api(Request $request){
    $from_req = $request->refercode;
    $response = Http::post('http://127.0.0.1:8001/api/yas/user/' . $from_req);
    $data = $response->json();
    $data_get = $data['customer_all'];

    Yasuser::updateOrCreate(
        ['refercode'=>$from_req],
        [
            'refercode' => $data_get['refer_code'] ?? null,
            'compitetor_name' => $data_get['customer_name'] ?? null,
            'total_inviter_number' => $data_get['invitor_number'] ?? null
        ]
        );

    return response()->json([
        "status"=>200,
        "message"=> "new info added"
    ]);
}

    public function ranking_api(){
        $comp = Yasuser::all();
        return response()->json($comp);
    }
}

    /**return response()-> json([
        "status"=> 200,
        "refer_code"=> $data,
        "from_request" => $from_req,
        "body" => $response->body(),
    ]);**/