<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiAnalysisProvider implements AiAnalysisProvider
{
    public function __construct(private readonly ?string $apiKey, private readonly string $model, private readonly int $timeoutSeconds = 20) {}

    public function review(array $evidence): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new \RuntimeException('AI provider is not configured.');
        }
        try {
            $response = Http::baseUrl('https://api.openai.com/v1')->withToken($this->apiKey)->acceptJson()->connectTimeout(5)->timeout($this->timeoutSeconds)->retry([200, 500])->post('/responses', ['model' => $this->model, 'input' => json_encode($evidence, JSON_THROW_ON_ERROR), 'text' => ['format' => ['type' => 'json_object']]]);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('AI provider request failed.', previous: $exception);
        }
        if (! $response->successful()) {
            throw new \RuntimeException('AI provider returned an unsuccessful response.');
        }
        $text = $response->json('output.0.content.0.text');
        if (! is_string($text)) {
            throw new \RuntimeException('AI provider returned an invalid response.');
        }
        try {
            $review = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('AI provider returned malformed JSON.', previous: $exception);
        }
        if (! is_array($review)) {
            throw new \RuntimeException('AI provider returned an invalid response.');
        }

        return $review;
    }
}
