<?php

namespace Tests\Feature;

use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\OpenAiAnalysisProvider;
use App\Services\Ai\GeminiAnalysisProvider;
use Tests\TestCase;

class OpenAiProviderBindingTest extends TestCase
{
    public function test_container_resolves_the_openai_provider_from_configuration(): void
    {
        config(['services.ai.provider' => 'openai', 'services.openai.key' => 'test-key', 'services.openai.model' => 'test-model', 'services.openai.timeout' => 7]);
        $this->app->forgetInstance(AiAnalysisProvider::class);

        $provider = $this->app->make(AiAnalysisProvider::class);

        $this->assertInstanceOf(OpenAiAnalysisProvider::class, $provider);
    }

    public function test_missing_key_resolves_safely_and_fails_only_when_review_is_requested(): void
    {
        config(['services.openai.key' => null]);
        $this->app->forgetInstance(AiAnalysisProvider::class);
        $provider = $this->app->make(AiAnalysisProvider::class);

        $this->expectException(\RuntimeException::class);
        $provider->review([]);
    }
    public function test_container_resolves_gemini_when_selected_by_configuration(): void
    {
        config([
            'services.ai.provider' => 'gemini',
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-test',
            'services.gemini.timeout' => 9,
        ]);
        $this->app->forgetInstance(AiAnalysisProvider::class);

        $provider = $this->app->make(AiAnalysisProvider::class);

        $this->assertInstanceOf(GeminiAnalysisProvider::class, $provider);
    }
}
