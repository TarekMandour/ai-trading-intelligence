<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $watchlist->exists ? 'Edit' : 'Create' }} EGX Watchlist</title>
<style>body{font:15px Arial,sans-serif;background:#f5f7fb;color:#172033;margin:0;padding:28px}main{max-width:900px;margin:auto}.panel{background:#fff;border:1px solid #e4e8f0;border-radius:12px;padding:20px}.field{margin:14px 0}input[type=text]{width:100%;padding:10px;border:1px solid #d0d5dd;border-radius:7px;box-sizing:border-box}button,.button{display:inline-block;border:0;border-radius:7px;padding:10px 14px;background:#2448d8;color:white;cursor:pointer;text-decoration:none}.stocks{max-height:520px;overflow:auto;border:1px solid #e4e8f0;border-radius:8px}.stock{display:flex;gap:10px;padding:10px;border-bottom:1px solid #eef0f4}.error{color:#b42318}</style></head>
<body><main><h1>{{ $watchlist->exists ? 'Edit watchlist' : 'Create watchlist' }}</h1><div class="panel">
@if($errors->any())<div class="error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ $watchlist->exists ? route('egx.watchlists.update', $watchlist) : route('egx.watchlists.store') }}">
@csrf @if($watchlist->exists) @method('PUT') @endif
<div class="field"><label for="name">Watchlist name</label><input id="name" name="name" type="text" maxlength="120" required value="{{ old('name', $watchlist->name) }}"></div>
<div class="field"><label for="stock-filter">Filter stocks</label><input id="stock-filter" type="text" placeholder="Search symbol or company name"></div>
<p>Select stocks and use the checkboxes to define the saved list.</p>
<div class="stocks" id="stocks">
@foreach($stocks as $stock)
<label class="stock" data-search="{{ strtolower($stock->symbol.' '.$stock->name.' '.$stock->arabic_name) }}">
<input type="checkbox" name="stock_ids[]" value="{{ $stock->id }}" @checked(in_array($stock->id, old('stock_ids', $selectedStockIds ?? [])))>
<span><strong>{{ $stock->symbol }}</strong> — {{ $stock->name ?: $stock->arabic_name ?: '—' }}</span>
</label>
@endforeach
</div>
<div class="field"><button type="submit">Save watchlist</button> <a href="{{ route('egx.watchlists.index') }}">Cancel</a></div>
</form></div></main>
<script>
document.getElementById('stock-filter').addEventListener('input', function () {
 const term=this.value.trim().toLowerCase();
 document.querySelectorAll('#stocks .stock').forEach(row => row.hidden=!row.dataset.search.includes(term));
});
</script></body></html>