<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ExternalAnalyticsService;
use Illuminate\Http\Request;
use App\Models\Game;

class AnalyticsController extends Controller
{
    public function __construct(
        private ExternalAnalyticsService $analytics
    ) {}

    public function show(Request $request, Game $game)
    
    {
        
        return response()->json([
            'status' => 200,
            'data' => $this->analytics->getForUser(
                $game,
                (int) $request->user()->id
            ),
        ]);
    }
}