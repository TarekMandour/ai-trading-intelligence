<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EGX Watchlists</title>
<style>
body{font:15px Arial,sans-serif;background:#f5f7fb;color:#172033;margin:0;padding:28px}main{max-width:1100px;margin:auto}.panel{background:#fff;border:1px solid #e4e8f0;border-radius:12px;padding:20px;margin:16px 0}a{color:#2448d8;text-decoration:none}button,.button{display:inline-block;border:0;border-radius:7px;padding:9px 13px;background:#2448d8;color:white;cursor:pointer;font:inherit}.danger{background:#a52b3b}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #eaecf0}.muted{color:#667085}.success{color:#087443}.actions{display:flex;gap:10px;align-items:center}
</style></head>
<body><main>
<h1>EGX Watchlists</h1><p class="muted">Manage your saved stock lists.</p>
@if(session('status'))<p class="success" role="status">{{ session('status') }}</p>@endif
<div class="panel">
<p><a class="button" href="{{ route('egx.watchlists.create') }}">Create watchlist</a> <a href="{{ route('analysis-runs.index') }}">Back to dashboard</a></p>
<table><thead><tr><th>Name</th><th>Stocks</th><th>Created</th><th>Actions</th></tr></thead><tbody>
@forelse($watchlists as $watchlist)
<tr><td><a href="{{ route('egx.watchlists.show', $watchlist) }}">{{ $watchlist->name }}</a></td><td>{{ $watchlist->stocks_count }}</td><td>{{ $watchlist->created_at?->format('Y-m-d H:i') }}</td><td class="actions"><a href="{{ route('egx.watchlists.edit', $watchlist) }}">Edit</a>
<form method="post" action="{{ route('egx.watchlists.destroy', $watchlist) }}" onsubmit="return confirm('Delete this watchlist?')">@csrf @method('DELETE')<button class="danger" type="submit">Delete</button></form></td></tr>
@empty<tr><td colspan="4" class="muted">No watchlists yet.</td></tr>@endforelse
</tbody></table>
{{ $watchlists->links() }}
</div></main></body></html>