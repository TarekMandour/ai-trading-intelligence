<?php

namespace Tests\Feature;

use App\Models\MarketSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThndrCaptureControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_is_disabled_by_default_and_token_is_rejected(): void
    {
        config(['thndr.capture_key' => 'test-capture-key']);

        $this->postJson('/api/thndr/token', ['token' => 'captured-token'], [
            'X-Capture-Key' => 'test-capture-key',
        ])->assertStatus(409)->assertJsonPath('message', 'capture_disabled');

        $this->assertDatabaseMissing('market_settings', ['key' => 'thndr_api_token']);
    }

    public function test_start_enables_capture_and_stop_disables_it(): void
    {
        $this->postJson('/api/thndr/capture/start')->assertOk()->assertJsonPath('enabled', true);
        $this->assertDatabaseHas('market_settings', ['key' => 'thndr_capture_enabled', 'value' => '1']);

        $this->postJson('/api/thndr/capture/stop')->assertOk()->assertJsonPath('enabled', false);
        $this->assertDatabaseHas('market_settings', ['key' => 'thndr_capture_enabled', 'value' => '0']);
    }

    public function test_status_masks_token_and_reports_capture_state(): void
    {
        MarketSetting::create(['key' => 'thndr_capture_enabled', 'value' => '1']);
        MarketSetting::create(['key' => 'thndr_api_token', 'value' => 'secret-token-123456']);
        MarketSetting::create(['key' => 'thndr_token_updated_at', 'value' => '2026-11-12T12:00:00+00:00']);

        $this->getJson('/api/thndr/capture/status')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('tokenMasked', '••••••123456')
            ->assertJsonMissingPath('token');
    }

    public function test_token_is_saved_only_when_capture_is_enabled_and_capture_key_is_valid(): void
    {
        config(['thndr.capture_key' => 'test-capture-key']);
        MarketSetting::create(['key' => 'thndr_capture_enabled', 'value' => '1']);

        $this->postJson('/api/thndr/token', ['token' => 'secret-token'], [
            'X-Capture-Key' => 'wrong-key',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('market_settings', ['key' => 'thndr_api_token']);

        $this->postJson('/api/thndr/token', ['token' => 'secret-token'], [
            'X-Capture-Key' => 'test-capture-key',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('market_settings', ['key' => 'thndr_api_token', 'value' => 'secret-token']);
    }
}
