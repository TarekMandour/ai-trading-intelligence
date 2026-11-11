<?php

namespace Tests\Feature;

use App\Services\Ai\GeminiAnalysisProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAnalysisProviderTest extends TestCase
{
    public function test_returns_structured_review_from_gemini_response(): void
    {
        Http::preventStrayRequests();
        $review = [
            'assessment' => 'ABSTAIN',
            'timeframe_alignment' => 'INSUFFICIENT',
            'confidence_label' => 'LOW',
            'supporting_evidence_ids' => [],
            'contradicting_evidence_ids' => [],
            'missing_confirmations' => [],
            'risk_observations' => [],
            'rationale' => 'Insufficient evidence.',
        ];
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($review)]]]]],
                'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 8],
            ]),
        ]);

        $result = (new GeminiAnalysisProvider('test-key', 'gemini-test'))->review(['evidence_ids' => ['e1']]);

        $this->assertSame($review, $result['review']);
        $this->assertSame('gemini-test', $result['metadata']['model']);
        $this->assertSame(12, $result['metadata']['usage']['promptTokenCount']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('x-goog-api-key', 'test-key')
            && ($request['generationConfig']['responseMimeType'] ?? null) === 'application/json'
            && isset($request['generationConfig']['responseJsonSchema']));
        Http::assertSentCount(1);
    }

    public function test_rejects_missing_credentials_without_http_request(): void
    {
        $this->expectException(\RuntimeException::class);
        (new GeminiAnalysisProvider(null, 'gemini-test'))->review([]);
    }

    public function test_rejects_unsuccessful_provider_http_response(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
        ]);

        $this->expectException(\RuntimeException::class);
        (new GeminiAnalysisProvider('test-key', 'gemini-test'))->review([]);
    }

    public function test_rejects_malformed_provider_json(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{bad']]]]],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        (new GeminiAnalysisProvider('test-key', 'gemini-test'))->review([]);
    }
}
