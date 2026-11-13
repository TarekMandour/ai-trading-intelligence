<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Analysis run #{{ $analysisRun->id }}</title></head>
<body>
<p><a href="{{ route('analysis-runs.index') }}">Back to runs</a></p>
<h1>Analysis run #{{ $analysisRun->id }}</h1>
<p>Status: <strong>{{ $analysisRun->status }}</strong> | AI: {{ $analysisRun->ai_status }}</p>
@if($analysisRun->audit)<h2>Run audit</h2><pre>{{ json_encode($analysisRun->audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@endif
@if(in_array($analysisRun->status, ['RUNNING', 'FAILED'], true) && $analysisRun->failure_reason)<p role="alert">{{ $analysisRun->status === 'RUNNING' ? 'Last attempt failure (retry pending):' : 'Failure:' }} {{ $analysisRun->failure_reason }}</p>@endif
@foreach($analysisRun->reports as $report)
<section>
<hr>
<h2>{{ $report->symbol }} — {{ $report->profile }} — {{ $report->status }}</h2>
<p><strong>Reasons:</strong> {{ implode(', ', $report->reasons ?? []) }}</p>
@if($report->data_quality_warnings)<p><strong>Data warnings:</strong> {{ implode(', ', $report->data_quality_warnings) }}</p>@endif
@php($ictResult = $report->timeframes['ict'] ?? [])
@php($watchlist = $ictResult['watchlist'] ?? null)
<section class="opportunity-card" aria-label="Opportunity summary">
<style>
.opportunity-card{border:1px solid #d1d5db;border-radius:12px;padding:16px;margin:16px 0;background:#fff;color:#111827}
.opportunity-card h3{margin:0 0 8px}.opportunity-card .status{display:inline-block;border-radius:999px;padding:4px 10px;background:#e5e7eb;font-weight:700;font-size:12px}
.opportunity-card .watch{background:#dbeafe;color:#1e40af}.opportunity-card .ready{background:#dcfce7;color:#166534}.opportunity-card .reject{background:#fee2e2;color:#991b1b}
.opportunity-card .levels{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin:12px 0}
.opportunity-card .level{background:#f3f4f6;padding:10px;border-radius:8px}.opportunity-card small{color:#4b5563}
</style>
<h3>Opportunity overview</h3>
<span class="status {{ $report->status === 'ENTERABLE' ? 'ready' : ($report->status === 'WATCHLIST' ? 'watch' : (in_array($report->status, ['REJECTED','FAILED'], true) ? 'reject' : '')) }}">{{ $report->status }}</span>
@if($report->status !== 'ENTERABLE' && is_array($watchlist))
<p><strong>Potential bullish setup — monitoring only.</strong> No order is authorized until the entry trigger is confirmed.</p>
<div class="levels">
<div class="level"><small>Last price</small><br><strong>{{ number_format((float) ($watchlist['reference_price'] ?? 0), 4) }}</strong></div>
<div class="level"><small>Zone low – high</small><br><strong>{{ number_format((float) ($watchlist['zone']['low'] ?? 0), 4) }} – {{ number_format((float) ($watchlist['zone']['high'] ?? 0), 4) }}</strong></div>
<div class="level"><small>Zone distance</small><br><strong>{{ $watchlist['distance_to_zone_pct'] ?? '—' }}%</strong></div>
</div>
<p><strong>Zone:</strong> {{ $watchlist['zone_type'] ?? '—' }}</p>
<p><strong>Structure:</strong> {{ $watchlist['structure']['context_bias'] ?? '—' }} / {{ $watchlist['structure']['structure_bias'] ?? '—' }} | MSS: {{ !empty($watchlist['structure']['mss_confirmed']) ? 'Confirmed' : 'Not confirmed' }}</p>
<p><strong>Evidence:</strong> {{ implode(', ', $watchlist['reasons'] ?? []) }}</p>
<p><strong>Required confirmation:</strong> {{ implode('; ', $watchlist['required_confirmation'] ?? []) }}</p>
<p><strong>Invalidation:</strong> {{ $watchlist['invalidation'] ?? '—' }}</p>
@elseif($report->status === 'ENTERABLE' && $report->opportunity && $report->opportunity->entry !== null)
<p><strong>Confirmed entry candidate.</strong></p>
<div class="levels">
<div class="level"><small>Entry</small><br><strong>{{ $report->opportunity->entry }}</strong></div>
<div class="level"><small>Stop loss</small><br><strong>{{ $report->opportunity->stop }}</strong></div>
<div class="level"><small>Target 1</small><br><strong>{{ $report->opportunity->target_1 }}</strong></div>
<div class="level"><small>Target 2</small><br><strong>{{ $report->opportunity->target_2 }}</strong></div>
</div>
@else
<p><strong>Decision:</strong> {{ $report->status }}. Review the evidence and reasons below; no entry is authorized.</p>
@endif
</section>
@if(isset($report->timeframes['ict']))
<h3>ICT Comprehensive</h3>
<p>Engine: {{ $report->timeframes['ict']['engine'] ?? 'ICT_COMPREHENSIVE' }} | Version: {{ $report->timeframes['ict']['version'] ?? '—' }} | Decision: {{ $report->timeframes['ict']['decision'] ?? '—' }}</p>
<details open><summary>ICT context, zones and scenario evidence</summary><pre>{{ json_encode($report->timeframes['ict'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
@endif
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
<h3>AI advisory review</h3>
<p><strong>Status:</strong> {{ $opportunity->aiReview->status ?? 'NOT_AVAILABLE' }}</p>
<p><small>Advisory only. The deterministic Laravel decision and trade levels remain authoritative; AI cannot approve a rejected setup or modify prices.</small></p>
@if($opportunity->aiReview)
<p>Provider: {{ $opportunity->aiReview->provider ?? '—' }} | Model: {{ $opportunity->aiReview->model ?? '—' }} | Latency: {{ $opportunity->aiReview->latency_ms ?? '—' }} ms</p>
<p>Prompt: {{ $opportunity->aiReview->prompt_version ?? '—' }} | Schema: {{ $opportunity->aiReview->schema_version ?? '—' }}</p>
@if($opportunity->aiReview->review)
@php($aiReview = $opportunity->aiReview->review)
<p><strong>Assessment:</strong> {{ $aiReview['assessment'] ?? '—' }} | <strong>Evidence strength:</strong> {{ $aiReview['confidence_label'] ?? '—' }} | <strong>Timeframe alignment:</strong> {{ $aiReview['timeframe_alignment'] ?? '—' }}</p>
<p><strong>Rationale:</strong> {{ $aiReview['rationale'] ?? '—' }}</p>
@if(!empty($aiReview['missing_confirmations']))<p><strong>Missing confirmations:</strong> {{ implode('; ', $aiReview['missing_confirmations']) }}</p>@endif
@if(!empty($aiReview['risk_observations']))<p><strong>Risk observations:</strong> {{ implode('; ', $aiReview['risk_observations']) }}</p>@endif
@if(!empty($aiReview['supporting_evidence_ids']))<p><strong>Supporting evidence:</strong> {{ implode(', ', $aiReview['supporting_evidence_ids']) }}</p>@endif
@if(!empty($aiReview['contradicting_evidence_ids']))<p><strong>Contradicting evidence:</strong> {{ implode(', ', $aiReview['contradicting_evidence_ids']) }}</p>@endif
@endif
@if($opportunity->aiReview->usage)<details><summary>Provider usage</summary><pre>{{ json_encode($opportunity->aiReview->usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
@endif
@endif
</section>
@endforeach
</body></html>
