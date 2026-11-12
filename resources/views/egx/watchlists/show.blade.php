<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $watchlist->name }} — EGX Watchlist</title>
<style>body{font:15px Arial,sans-serif;background:#f5f7fb;color:#172033;margin:0;padding:28px}main{max-width:900px;margin:auto}.panel{background:#fff;border:1px solid #e4e8f0;border-radius:12px;padding:20px}a{color:#2448d8}.stock{padding:10px;border-bottom:1px solid #eef0f4}.muted{color:#667085}</style></head>
<body><main><p><a href="{{ route('egx.watchlists.index') }}">← Watchlists</a></p><h1>{{ $watchlist->name }}</h1><p class="muted">{{ $watchlist->stocks->count() }} stock(s)</p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
<div class="panel"><p><a href="{{ route('egx.watchlists.edit', $watchlist) }}">Edit watchlist</a> · <a href="{{ route('analysis-runs.index') }}">Dashboard / Scanner</a></p>
@forelse($watchlist->stocks as $stock)<div class="stock"><strong>{{ $loop->iteration }}. {{ $stock->symbol }}</strong> — {{ $stock->name ?: $stock->arabic_name ?: '—' }}</div>
@empty<p class="muted">This watchlist has no stocks.</p>@endforelse
</div></main></body></html>