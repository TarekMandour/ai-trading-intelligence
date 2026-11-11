<?php

namespace App\Providers;

use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\OpenAiAnalysisProvider;
use App\Services\Ai\GeminiAnalysisProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiAnalysisProvider::class, function (): AiAnalysisProvider {
            return match (config('services.ai.provider', 'openai')) {
                'openai' => new OpenAiAnalysisProvider(
                    config('services.openai.key'),
                    config('services.openai.model'),
                    config('services.openai.timeout'),
                    config('services.openai.max_output_tokens'),
                ),
                'gemini' => new GeminiAnalysisProvider(
                    config('services.gemini.key'),
                    config('services.gemini.model'),
                    config('services.gemini.timeout'),
                    config('services.gemini.max_output_tokens'),
                ),
                default => throw new \\InvalidArgumentException('Unsupported AI provider configured.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
