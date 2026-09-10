<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RefercodeAlreadyUsedException;
use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameUser;
use App\Models\IdempotencyKey;
use App\Models\Yasuser;
use App\Services\Game\GameRegistrationService;
use App\Services\Ranking\RankingService;
use App\Services\Referral\ReferralService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use DomainException;
use Throwable;

class CompetitionController extends Controller
{
    public function __construct(
        private GameRegistrationService $registration,
        private RankingService $ranking,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $query = Game::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $games = $query
            ->orderBy('start_date')
            ->paginate($perPage);

        $games->through(fn (Game $game) => $this->resource($game));

        return response()->json([
            'status' => 200,
            'data' => $games,
        ]);
    }

    public function show(Game $game)
    {
        return response()->json([
            'status' => 200,
            'data' => [
                'competition' => $this->resource($game),
            ],
        ]);
    }

    public function join(Request $request, Game $game)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'referral_code' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],
            'consents' => ['required', 'array'],
            'consents.terms' => ['required', 'accepted'],
            'consents.sms' => ['sometimes', 'boolean'],
            'consents.future_competitions' => ['sometimes', 'boolean'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            return response()->json([
                'status' => 400,
                'message' => 'Idempotency-Key header is required.',
            ], 400);
        }

        $user = $request->user();
        $endpoint = $request->path();

        $existingKey = IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $idempotencyKey)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existingKey) {
            if ($existingKey->response_body !== null) {
                return response()->json(
                    $existingKey->response_body,
                    $existingKey->response_status ?? 200
                );
            }

            if ($existingKey->updated_at?->lt(now()->subMinutes(10))) {
                $existingKey->delete();
            } else {
                return response()->json([
                    'status' => 409,
                    'message' => 'This request is already being processed.',
                ], 409);
            }
        }

        // Reserve the idempotency key before doing the external API call.
        try {
            IdempotencyKey::create([
                'user_id' => $user->id,
                'key' => $idempotencyKey,
                'endpoint' => $endpoint,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existingKey = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('key', $idempotencyKey)
                ->where('endpoint', $endpoint)
                ->first();

            if ($existingKey?->response_body !== null) {
                return response()->json(
                    $existingKey->response_body,
                    $existingKey->response_status ?? 200
                );
            }

            return response()->json([
                'status' => 409,
                'message' => 'This request is already being processed.',
            ], 409);
        }

        $game->refresh();
        $now = now();

        if (
            !$game->is_active ||
            !$game->start_date ||
            !$game->end_date ||
            $now->lt($game->start_date) ||
            $now->gt($game->end_date)
        ) {
            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 409,
                    'message' => 'This competition is not currently accepting joins.',
                ],
                409
            );
        }

        if ($this->normalizePhone($validated['phone']) !== $this->normalizePhone((string) ($user->phone_number ?? ''))) {
            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 422,
                    'message' => 'The phone number must match your account phone number.',
                ],
                422
            );
        }

        $refercode = ReferralService::normalizeRefercode($validated['referral_code']);

        try {
            $result = $this->registration->verifyAndRegister(
                $user->id,
                $game,
                $refercode,
            );

            Cache::forget("game:{$game->id}:ranking");

            $body = [
                'status' => 201,
                'message' => 'Competition joined successfully.',
                'data' => [
                    'competition' => $this->resource($result['game']),
                    'participation' => [
                        'id' => $result['registration']->id,
                        'referral_code' => $result['registration']->refercode,
                        'verified' => true,
                        'joined_at' => $result['registration']->created_at,
                    ],
                ],
            ];

            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                $body,
                201
            );
        } catch (RefercodeAlreadyUsedException $e) {
            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 409,
                    'message' => $e->getMessage(),
                ],
                409
            );
        } catch (DomainException $e) {
            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 409,
                    'message' => $e->getMessage(),
                ],
                409
            );
        } catch (RefercodeNotFoundException $e) {
            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 404,
                    'message' => 'This referral code is not recognized.',
                ],
                404
            );
        } catch (ReferralServiceUnavailableException $e) {
            Log::error('Referral service unavailable during competition join', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
            ]);

            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 503,
                    'message' => 'Unable to verify referral code at this time.',
                ],
                503
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unexpected competition join error', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'refercode' => $refercode,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->finishIdempotentRequest(
                $user->id,
                $idempotencyKey,
                $endpoint,
                [
                    'status' => 500,
                    'message' => 'Unable to complete your registration right now.',
                ],
                500
            );
        }
    }

    public function me(Request $request, Game $game)
    {
        $participation = GameUser::query()
            ->where('user_id', $request->user()->id)
            ->where('game_id', $game->id)
            ->first();

        if (!$participation) {
            return response()->json([
                'status' => 404,
                'message' => 'You have not joined this competition.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'participation' => $participation,
            ],
        ]);
    }

    public function leaderboard(Game $game)
    {
        $ranking = $this->ranking
            ->getRanking($game->id)
            ->map(fn (GameUser $participant) => $this->rankResource($participant))
            ->values();

        return response()->json([
            'status' => 200,
            'data' => [
                'competition' => $this->resource($game),
                'leaderboard' => $ranking,
            ],
        ]);
    }

    public function myLeaderboard(Request $request, Game $game)
    {
        $participant = GameUser::query()
            ->where('user_id', $request->user()->id)
            ->where('game_id', $game->id)
            ->first();

        if (!$participant) {
            return response()->json([
                'status' => 404,
                'message' => 'You have not joined this competition.',
            ], 404);
        }

        $ranking = $this->ranking->getRanking($game->id, 10000);
        $current = $ranking->firstWhere('id', $participant->id);

        return response()->json([
            'status' => 200,
            'data' => [
                'ranking' => $current
                    ? $this->rankResource($current)
                    : null,
            ],
        ]);
    }

    public function referral(Request $request, Game $game)
    {
        $participant = GameUser::query()
            ->where('user_id', $request->user()->id)
            ->where('game_id', $game->id)
            ->first();

        if (!$participant) {
            return response()->json([
                'status' => 404,
                'message' => 'You have not joined this competition.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'referral_code' => $participant->refercode,
                'verified' => (bool) $participant->refercode_verified,
            ],
        ]);
    }

    public function referrals(Request $request, Game $game)
    {
        $participant = GameUser::query()
            ->where('user_id', $request->user()->id)
            ->where('game_id', $game->id)
            ->first();

        if (!$participant) {
            return response()->json([
                'status' => 404,
                'message' => 'You have not joined this competition.',
            ], 404);
        }

        $yasuser = Yasuser::query()
            ->where('game_id', $game->id)
            ->where('refercode', $participant->refercode)
            ->first();

        return response()->json([
            'status' => 200,
            'data' => [
                'referrals' => [[
                    'refer_code' => $yasuser?->refercode,
                    'name' => $yasuser?->compitetor_name,
                    'invitor_number' => $yasuser?->total_inviter_number,
                    'last_synced_at' => $yasuser?->last_synced_at,
                ]],
            ],
        ]);
    }

    private function finishIdempotentRequest(
        int $userId,
        string $key,
        string $endpoint,
        array $body,
        int $status,
    ) {
        IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->where('endpoint', $endpoint)
            ->update([
                'response_status' => $status,
                'response_body' => $body,
            ]);

        return response()->json($body, $status);
    }

    private function resource(Game $game): array
    {
        $now = now();

        $status = 'draft';

        if ($game->start_date && $game->end_date) {
            if ($now->lt($game->start_date)) {
                $status = 'upcoming';
            } elseif ($now->gt($game->end_date)) {
                $status = 'completed';
            } elseif ($now->diffInHours($game->end_date, false) <= 24) {
                $status = 'ending_soon';
            } else {
                $status = 'live';
            }
        }

        return [
            'id' => $game->public_id,
            'name' => $game->name,
            'code' => $game->code,
            'status' => $status,
            'is_active' => (bool) $game->is_active,
            'start_at' => $game->start_date?->toISOString(),
            'end_at' => $game->end_date?->toISOString(),
            'participants' => GameUser::query()
                ->where('game_id', $game->id)
                ->where('refercode_verified', true)
                ->count(),
            'prizes' => [
                'first_place_prize' => $game->first_place_prize,
                'second_place_prize' => $game->second_place_prize,
                'third_place_prize' => $game->third_place_prize,
            ],
            'rules' => $game->competition_rules,
            'winning_instructions' => $game->winning_instructions,
        ];
    }


    public function ranking(Game $game)
{
    $ranking = $this->ranking
        ->getRanking($game->id)
        ->map(function (GameUser $participant) {
            return $this->rankResource($participant);
        })
        ->values();

    return response()->json([
        'status' => 200,
        'message' => 'Competition ranking retrieved successfully.',
        'data' => [
            'competition' => [
                'public_id' => $game->public_id,
                'name' => $game->name,
            ],
            'ranking' => $ranking,
        ],
    ]);
}

   private function rankResource(GameUser $participant): array
{
    return [
        'rank' => $participant->current_rank,

        'user_id' => $participant->user_id,

        'name' => $participant->customer_name
            ?? $participant->user?->name,

        'refercode' => $participant->refercode,

        'invitor_number' => $participant->invitor_number,

        'previous_rank' => $participant->previous_rank,

        'rank_change' => $participant->rank_change,

        'rank_movement' => $participant->rank_movement,
    ];
}
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
