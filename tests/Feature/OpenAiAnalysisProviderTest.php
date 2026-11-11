<?php

namespace Tests\Feature;

use App\Services\Ai\OpenAiAnalysisProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiAnalysisProviderTest extends TestCase
{
    public function test_returns_structured_review_from_openai_response(): void
    {
        Http::preventStrayRequests();
        $payload = ['output' => [['content' => [['text' => '{"assessment":"ABSTAIN","timeframe_alignment":"INSUFFICIENT","confidence_label":"LOW","supporting_evidence_ids":[],"contradicting_evidence_ids":[],"missing_confirmations":[],"risk_observations":[],"rationale":"Insufficient evidence."}']]]]];
        Http::fake(['api.openai.com/v1/responses' => Http::response($payload)]);

        $review = (new OpenAiAnalysisProvider('test-key', 'test-model'))->review(['evidence_ids' => ['e1']]);

        $this->assertSame('ABSTAIN', $review['review']['assessment']);
        $this->assertSame('test-model', $review['metadata']['model']);
        Http::assertSent(fn ($request): bool => ($request['text']['format']['type'] ?? null) === 'json_schema' && isset($request['instructions']));
        Http::assertSentCount(1);
    }

    public function test_rejects_missing_credentials_without_http_request(): void
    {
        $this->expectException(\RuntimeException::class);
        (new OpenAiAnalysisProvider(null, 'test-model'))->review([]);
    }

    public function test_rejects_malformed_provider_json(): void
    {
        Http::preventStrayRequests();
        $payload = [
            'output' => [
                [
                    'content' => [
                        ['text' => '{bad'],
                    ],
                ],
            ],
        ];
        Http::fake(['api.openai.com/v1/responses' => Http::response($payload)]);
        $this->expectException(\RuntimeException::class);
        (new OpenAiAnalysisProvider('test-key', 'test-model'))->review([]);
    }
}
