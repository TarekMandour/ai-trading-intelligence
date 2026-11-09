<?php

namespace App\Providers;

use App\Services\Ai\AiAnalysisProvider;
use App\Services\Ai\OpenAiAnalysisProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiAnalysisProvider::class, fn (): OpenAiAnalysisProvider => new OpenAiAnalysisProvider(
            config('services.openai.key'),
            config('services.openai.model'),
            config('services.openai.timeout'),
            config('services.openai.max_output_tokens'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
