<?php

namespace Tests\Unit;

use App\Exceptions\RefercodeNotFoundException;
use App\Exceptions\ReferralServiceUnavailableException;
use App\Models\Game;
use App\Models\Yasuser;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_the_external_referral_contract(): void
    {
        $game = Game::create([
            'name' => 'Test Competition',
            'code' => 'test-' . uniqid(),
            'is_active' => true,
            'external_api_base_url' => 'https://example.test/api/yas',
        ]);

        Http::fake([
            'https://example.test/api/yas/ABC823' => Http::response([
                'status' => 200,
                'customer_all' => [
                    'refer_code' => 'abc823',
                    'customer_name' => 'john doe',
                    'invitor_number' => 200000000000,
                ],
            ], 200),
        ]);

        $result = app(ReferralService::class)->verify(' abc823 ', $game);

        $this->assertSame('ABC823', $result['refer_code']);
        $this->assertSame('john doe', $result['customer_name']);
        $this->assertSame(200000000000, $result['invitor_number']);
    }

    public function test_it_syncs_large_inviter_numbers_without_overflow(): void
    {
        $game = Game::create([
            'name' => 'Test Competition',
            'code' => 'test-' . uniqid(),
            'is_active' => true,
            'external_api_base_url' => 'https://example.test/api/yas',
        ]);

        Http::fake([
            'https://example.test/api/yas/ABC823' => Http::response([
                'status' => 200,
                'customer_all' => [
                    'refer_code' => 'abc823',
                    'customer_name' => 'john doe',
                    'invitor_number' => 200000000000,
                ],
            ], 200),
        ]);

        app(ReferralService::class)->fetchAndSync('ABC823', $game);

        $this->assertDatabaseHas('yasuser', [
            'game_id' => $game->id,
            'refercode' => 'ABC823',
            'total_inviter_number' => 200000000000,
        ]);

        $this->assertSame(
            200000000000,
            Yasuser::where('game_id', $game->id)->value('total_inviter_number')
        );
    }

    public function test_it_maps_external_404_to_refercode_not_found(): void
    {
        $game = Game::create([
            'name' => 'Test Competition',
            'code' => 'test-' . uniqid(),
            'is_active' => true,
            'external_api_base_url' => 'https://example.test/api/yas',
        ]);

        Http::fake([
            'https://example.test/api/yas/ABC404' => Http::response([
                'status' => 404,
                'message' => 'Refercode not found',
            ], 404),
        ]);

        $this->expectException(RefercodeNotFoundException::class);

        app(ReferralService::class)->verify('ABC404', $game);
    }

    public function test_it_rejects_inconsistent_external_refercode(): void
    {
        $game = Game::create([
            'name' => 'Test Competition',
            'code' => 'test-' . uniqid(),
            'is_active' => true,
            'external_api_base_url' => 'https://example.test/api/yas',
        ]);

        Http::fake([
            'https://example.test/api/yas/ABC823' => Http::response([
                'status' => 200,
                'customer_all' => [
                    'refer_code' => 'ABC999',
                    'customer_name' => 'Wrong user',
                    'invitor_number' => 10,
                ],
            ], 200),
        ]);

        $this->expectException(ReferralServiceUnavailableException::class);

        app(ReferralService::class)->verify('ABC823', $game);
    }
}
