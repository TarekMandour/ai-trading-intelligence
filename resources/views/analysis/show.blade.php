<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Analysis run #{{ $analysisRun->id }}</title></head>
<body>
<p><a href="{{ route('analysis-runs.index') }}">Back to runs</a></p>
<h1>Analysis run #{{ $analysisRun->id }}</h1>
<p>Status: <strong>{{ $analysisRun->status }}</strong> | AI: {{ $analysisRun->ai_status }}</p>
@if($analysisRun->audit)<h2>Run audit</h2><pre>{{ json_encode($analysisRun->audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@endif
@if($analysisRun->status === 'FAILED' && $analysisRun->failure_reason)<p role="alert">Failure: {{ $analysisRun->failure_reason }}</p>@endif
@foreach($analysisRun->reports as $report)
<section>
<hr>
<h2>{{ $report->symbol }} — {{ $report->profile }} — {{ $report->status }}</h2>
<p><strong>Reasons:</strong> {{ implode(', ', $report->reasons ?? []) }}</p>
@if($report->data_quality_warnings)<p><strong>Data warnings:</strong> {{ implode(', ', $report->data_quality_warnings) }}</p>@endif
<h3>Market context and timeframes</h3>
<pre>{{ json_encode($report->timeframes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@if($report->opportunity)
@php($opportunity = $report->opportunity)
<h3>Trade plan</h3>
<p>Decision: {{ $opportunity->decision }}</p>
<p>Scenario: {{ $opportunity->scenario ?? 'No confirmed scenario' }}</p>
@if($opportunity->entry !== null)
<ul>
<li>Entry: {{ $opportunity->entry }}</li>
<li>Stop loss: {{ $opportunity->stop }}</li>
<li>Target 1: {{ $opportunity->target_1 }}</li>
<li>Target 2: {{ $opportunity->target_2 }}</li>
</ul>
@endif
@php($costs = $report->timeframes['execution_costs'] ?? [])
@if(isset($costs['estimated_entry']))
<h4>Execution estimate (current order-book snapshot)</h4>
<ul>
<li>Conservative estimated entry: {{ $costs['estimated_entry'] }}</li>
<li>Buy VWAP: {{ $costs['buy_vwap'] ?? '—' }}</li>
<li>Sell VWAP: {{ $costs['sell_vwap'] ?? '—' }}</li>
<li>Estimated round-trip fees: {{ $costs['fee'] ?? '—' }}</li>
<li>Spread cost: {{ $costs['spread'] ?? '—' }}</li>
<li>Slippage estimate: {{ $costs['slippage'] ?? '—' }}</li>
<li>Cost-adjusted reward/risk: {{ $costs['net_risk_reward'] ?? '—' }}</li>
</ul>
<p><small>Order-book depth is a live snapshot, not historical liquidity or a fill guarantee.</small></p>
@endif
<h3>Evidence</h3>
@foreach($opportunity->evidence as $evidence)
<details><summary>{{ $evidence->kind }}</summary><pre>{{ json_encode($evidence->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
@endforeach
<h3>AI review</h3>
<p>Status: {{ $opportunity->aiReview->status ?? 'NOT_AVAILABLE' }}</p>
@if($opportunity->aiReview)
<p>Provider: {{ $opportunity->aiReview->provider ?? '—' }} | Model: {{ $opportunity->aiReview->model ?? '—' }} | Latency: {{ $opportunity->aiReview->latency_ms ?? '—' }} ms</p>
@if($opportunity->aiReview->usage)<details><summary>Provider usage</summary><pre>{{ json_encode($opportunity->aiReview->usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
@endif
@if($opportunity->aiReview?->review)<pre>{{ json_encode($opportunity->aiReview->review, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@endif
@endif
</section>
@endforeach
</body></html>
