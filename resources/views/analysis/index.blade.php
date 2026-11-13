<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Trading Intelligence</title>
    <style>
        :root{font-family:Inter,Arial,sans-serif;color:#172033;background:#f5f7fb}*{box-sizing:border-box}body{margin:0;padding:28px;max-width:1440px;margin-inline:auto}
        h1{margin:0 0 6px}h2{margin:0 0 16px;font-size:20px}.muted{color:#667085}.panel{background:#fff;border:1px solid #e4e8f0;border-radius:12px;padding:20px;margin:18px 0;box-shadow:0 2px 8px #18223008}
        .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.grid{display:grid;grid-template-columns: minmax(280px,1fr) minmax(320px,1.5fr);gap:18px}
        button,select,input{font:inherit}button{border:0;border-radius:8px;padding:10px 15px;background:#2448d8;color:white;cursor:pointer}button.secondary{background:#e9edfa;color:#25345b}button.danger{background:#a52b3b}button:disabled{opacity:.45;cursor:not-allowed}
        input[type=text],select{border:1px solid #d0d5dd;border-radius:8px;padding:10px;min-height:40px}label{display:inline-flex;gap:7px;align-items:center}
        .status{font-weight:600}.stock-list{border:1px solid #e4e8f0;border-radius:8px;max-height:360px;overflow:auto;margin-top:12px}
        .stock-row{display:flex;gap:10px;padding:9px 12px;border-bottom:1px solid #eef0f4;align-items:center}.stock-row:last-child{border:0}.stock-row small{color:#667085}
        .result-card{border:1px solid #e4e8f0;border-radius:8px;padding:12px;margin:10px 0}.pill{display:inline-block;background:#eef2ff;border-radius:20px;padding:3px 8px;font-size:12px}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#101828;color:#e6edf7;padding:14px;border-radius:8px;max-height:520px;overflow:auto}
        .message{min-height:22px;margin:10px 0;color:#475467}.success{color:#087443}.error{color:#b42318}.inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        @media(max-width:800px){body{padding:14px}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <h1>AI Trading Intelligence</h1>
    <p class="muted">EGX scanner powered by Thndr market data and the ICT Comprehensive engine.</p>
    <p><a href="{{ route('egx.watchlists.index') }}">Manage EGX Watchlists</a></p>

    <section class="panel">
        <h2>Thndr Token Capture</h2>
        <div class="toolbar">
            <span>Status: <strong id="thndr-capture-state">Loading…</strong></span>
            <span>Token: <code id="thndr-token-masked">Not received</code></span>
            <span>Last update: <span id="thndr-token-updated">—</span></span>
            <button type="button" id="thndr-capture-start">Start Capture</button>
            <button type="button" class="secondary" id="thndr-capture-stop" disabled>Stop Capture</button>
        </div>
        <p class="message" id="thndr-capture-message" role="status" aria-live="polite"></p>
    </section>

    <section class="panel">
        <div class="toolbar" style="justify-content:space-between">
            <div><h2 style="margin-bottom:4px">EGX Market Scanner</h2><span class="muted">Sync the market, build a watchlist, then run the selected ICT profiles.</span></div>
            <button type="button" id="sync-market">Sync Thndr Market</button>
        </div>
        <p class="message" id="sync-message" role="status"></p>
        <div class="grid">
            <div>
                <h3>1. Watchlists</h3>
                <div class="inline">
                    <select id="watchlist-select" style="min-width:220px;flex:1"><option value="">Select a watchlist</option></select>
                    <button type="button" class="secondary" id="load-watchlists">Refresh</button>
                </div>
                <div class="inline" style="margin-top:10px">
                    <input type="text" id="new-watchlist-name" placeholder="New watchlist name" style="flex:1;min-width:180px">
                    <button type="button" id="create-watchlist">Create from selected stocks</button>
                </div>
                <p class="muted" id="watchlist-count">No watchlist selected.</p>
                <div class="inline">
                    <button type="button" class="secondary" id="save-watchlist" disabled>Save stock selection</button>
                    <button type="button" class="secondary" id="select-all">Select all shown</button>
                    <button type="button" class="secondary" id="clear-selection">Clear</button>
                </div>
            </div>
            <div>
                <h3>2. Choose EGX stocks</h3>
                <div class="inline">
                    <input type="text" id="stock-search" placeholder="Search symbol or company name" style="flex:1;min-width:180px">
                    <button type="button" class="secondary" id="search-stocks">Search</button>
                </div>
                <div class="stock-list" id="stock-list"><p class="muted" style="padding:12px">Sync market data to load stocks.</p></div>
            </div>
        </div>
        <hr style="border:0;border-top:1px solid #eaecf0;margin:22px 0">
        <h3>3. Run Scanner</h3>
        <div class="toolbar" style="margin:12px 0">
            <label><input type="checkbox" name="profile" value="Scalping" checked> Scalping</label>
            <label><input type="checkbox" name="profile" value="Intraday" checked> Intraday</label>
            <label><input type="checkbox" name="profile" value="Swing" checked> Swing</label>
            <button type="button" id="run-scan">Run Scan</button>
            <button type="button" class="secondary" id="scan-history" disabled>Scan History</button>
        </div>
        <p class="message" id="scan-message" role="status" aria-live="polite"></p>
        <div id="scan-results"></div>
    </section>

    <section class="panel">
        <h2>Historical crypto analysis runs</h2>
        @if(session('status'))<p>{{ session('status') }}</p>@endif
        <form method="post" action="{{ route('analysis-runs.store') }}">@csrf<button type="submit">Start Crypto Analysis</button></form>
        <ul>
            @forelse($runs as $run)
                <li><a href="{{ route('analysis-runs.show', $run) }}">Run #{{ $run->id }}</a> — {{ $run->status }} / AI: {{ $run->ai_status }}</li>
            @empty
                <li>No runs yet.</li>
            @endforelse
        </ul>
        {{ $runs->links() }}
    </section>

<script>
(() => {
    const api = @json(url('/api'));
    const $ = id => document.getElementById(id);
    const jsonHeaders = {'Accept':'application/json','Content-Type':'application/json'};
    let stocks = [], watchlists = [], selectedIds = new Set(), currentWatchlist = null;
    async function request(path, options={}) {
        const response = await fetch(api + path, {headers:jsonHeaders, ...options});
        const data = await response.json().catch(()=>({}));
        if (!response.ok) throw new Error(data.message || data.error || ('Request failed ('+response.status+')'));
        return data;
    }
    function message(id,text,type=''){ $(id).textContent=text; $(id).className='message '+type; }

    // Token capture controls
    function renderCapture(data) {
        const enabled=Boolean(data.enabled);
        $('thndr-capture-state').textContent=enabled?'Running':'Stopped';
        $('thndr-capture-start').disabled=enabled; $('thndr-capture-stop').disabled=!enabled;
        $('thndr-token-masked').textContent=data.tokenMasked||'Not received';
        $('thndr-token-updated').textContent=data.tokenUpdatedAt||'—';
    }
    async function refreshCapture(){try{renderCapture(await request('/thndr/capture/status'));}catch(e){message('thndr-capture-message',e.message,'error');}}
    async function setCapture(action){
        $('thndr-capture-start').disabled=true;$('thndr-capture-stop').disabled=true;
        try{renderCapture(await request('/thndr/capture/'+action,{method:'POST'}));message('thndr-capture-message',action==='start'?'Capture enabled. Waiting for the Chrome extension…':'Capture stopped.','success');
            if(action==='start'){let n=0;const timer=setInterval(async()=>{await refreshCapture();if(++n>=10)clearInterval(timer);},3000);}
        }catch(e){message('thndr-capture-message',e.message,'error');await refreshCapture();}
    }
    $('thndr-capture-start').addEventListener('click',()=>setCapture('start'));
    $('thndr-capture-stop').addEventListener('click',()=>setCapture('stop'));

    // Market universe and watchlists
    function renderStocks(){
        const host=$('stock-list');host.replaceChildren();
        if(!stocks.length){host.textContent='No stocks found. Sync the market first.';return;}
        stocks.forEach(stock=>{
            const row=document.createElement('label');row.className='stock-row';
            const check=document.createElement('input');check.type='checkbox';check.checked=selectedIds.has(stock.asset_id);
            check.addEventListener('change',()=>{check.checked?selectedIds.add(stock.asset_id):selectedIds.delete(stock.asset_id);updateWatchlistCount();});
            const symbol=stock.reuters||stock.symbol||'';
            const title=document.createElement('span');title.textContent=[stock.arabic_name||stock.name,symbol].filter(Boolean).join(' — ')||'Company details unavailable';
            row.append(check,title);host.append(row);
        });
        updateWatchlistCount();
    }
    function updateWatchlistCount(){$('watchlist-count').textContent=selectedIds.size+' stock(s) selected'+(currentWatchlist?' for '+currentWatchlist.name:'')+'.';$('save-watchlist').disabled=!currentWatchlist;}
    async function loadStocks(search=''){
        const qs=search?'?search='+encodeURIComponent(search):'';
        stocks=await request('/egx/stocks'+qs);renderStocks();
    }
    async function loadWatchlists(chooseId=null){
        watchlists=await request('/egx/watchlists');const select=$('watchlist-select');select.replaceChildren(new Option('Select a watchlist',''));
        watchlists.forEach(w=>select.add(new Option(w.name+' ('+(w.stocks?.length||0)+')',w.id)));
        const chosen=chooseId||select.value;if(chosen&&watchlists.some(w=>String(w.id)===String(chosen)))select.value=String(chosen);
        applyWatchlist();
    }
    function applyWatchlist(){
        const id=$('watchlist-select').value;currentWatchlist=watchlists.find(w=>String(w.id)===id)||null;
        selectedIds=new Set((currentWatchlist?.stocks||[]).map(s=>s.asset_id));$('scan-history').disabled=!currentWatchlist;
        renderStocks();updateWatchlistCount();
    }
    $('sync-market').addEventListener('click',async()=>{
        $('sync-market').disabled=true;message('sync-message','Syncing Thndr market watch…');
        try{const d=await request('/egx/universe/sync',{method:'POST'});message('sync-message','Market synced. '+d.stocks_synced+' stock(s) imported.','success');await loadStocks($('stock-search').value);}
        catch(e){message('sync-message',e.message,'error');}finally{$('sync-market').disabled=false;}
    });
    $('load-watchlists').addEventListener('click',()=>loadWatchlists().catch(e=>message('sync-message',e.message,'error')));
    $('watchlist-select').addEventListener('change',applyWatchlist);
    $('search-stocks').addEventListener('click',()=>loadStocks($('stock-search').value).catch(e=>message('sync-message',e.message,'error')));
    $('stock-search').addEventListener('keydown',e=>{if(e.key==='Enter')$('search-stocks').click();});
    $('select-all').addEventListener('click',()=>{stocks.forEach(s=>selectedIds.add(s.asset_id));renderStocks();});
    $('clear-selection').addEventListener('click',()=>{selectedIds.clear();renderStocks();});
    $('create-watchlist').addEventListener('click',async()=>{
        const name=$('new-watchlist-name').value.trim();if(!name){message('sync-message','Enter a watchlist name.','error');return;}
        if(!selectedIds.size){message('sync-message','Select at least one stock first.','error');return;}
        try{const w=await request('/egx/watchlists',{method:'POST',body:JSON.stringify({name,asset_ids:[...selectedIds]})});$('new-watchlist-name').value='';await loadWatchlists(w.id);message('sync-message','Watchlist created.','success');}
        catch(e){message('sync-message',e.message,'error');}
    });
    $('save-watchlist').addEventListener('click',async()=>{
        if(!currentWatchlist)return;
        try{await request('/egx/watchlists/'+currentWatchlist.id,{method:'PUT',body:JSON.stringify({asset_ids:[...selectedIds]})});await loadWatchlists(currentWatchlist.id);message('sync-message','Watchlist saved.','success');}
        catch(e){message('sync-message',e.message,'error');}
    });

    // Execute ICT scan and render its response
    function renderResults(data){
        const host=$('scan-results');host.replaceChildren();
        const heading=document.createElement('p');heading.innerHTML='<strong>Scan #'+data.run_id+'</strong> · '+(data.watchlist||'Watchlist')+' · '+(data.stocks?.length||0)+' stock(s)';host.append(heading);
        (data.stocks||[]).forEach(stock=>{
            const card=document.createElement('div');card.className='result-card';
            const symbol=stock.reuters||stock.symbol||'';
            const title=document.createElement('h4');title.textContent=[stock.arabic_name||stock.name,symbol].filter(Boolean).join(' — ')||'Stock details unavailable';card.append(title);
            Object.entries(stock.profiles||{}).forEach(([profile,result])=>{
                const line=document.createElement('p');const status=result.status||result.decision||'UNKNOWN';line.innerHTML='<span class="pill">'+profile+'</span> <strong>'+status+'</strong>';
                const reasons=result.reasons||[];if(reasons.length)line.textContent+=' · '+reasons.join(', ');
                card.append(line);
                if(result.context){
                    const context=document.createElement('p');
                    context.textContent='Context: '+(result.context.bias||'—')+' | Structure: '+(result.context.structure_bias||'—');
                    card.append(context);
                }
                if(Array.isArray(result.scenarios)){
                    result.scenarios.forEach(scenario=>{
                        const details=document.createElement('details');details.open=true;
                        const summary=document.createElement('summary');
                        summary.textContent=(scenario.scenario||'ICT scenario')+' — '+(scenario.status||'UNKNOWN');
                        details.append(summary);
                        const reasonList=document.createElement('ul');
                        (scenario.reasons||[]).forEach(reason=>{const item=document.createElement('li');item.textContent=reason;reasonList.append(item);});
                        if((scenario.reasons||[]).length)details.append(reasonList);
                        if(scenario.candidate){const plan=document.createElement('pre');plan.textContent=JSON.stringify(scenario.candidate,null,2);details.append(plan);}
                        if(scenario.evidence){const evidence=document.createElement('pre');evidence.textContent=JSON.stringify(scenario.evidence,null,2);details.append(evidence);}
                        card.append(details);
                    });
                }
                if(result.sync){const sync=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Candle sync diagnostics';sync.append(summary);const pre=document.createElement('pre');pre.textContent=JSON.stringify(result.sync,null,2);sync.append(pre);card.append(sync);}
                if(result.setup_evidence||result.entry_evidence||result.advanced_evidence){
                    const evidence=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Full ICT evidence';evidence.append(summary);const pre=document.createElement('pre');pre.textContent=JSON.stringify({setup:result.setup_evidence,entry:result.entry_evidence,advanced:result.advanced_evidence},null,2);evidence.append(pre);card.append(evidence);
                }
            });host.append(card);
        });
        if(!(data.stocks||[]).length)host.textContent='No stock results returned.';
    }
    $('run-scan').addEventListener('click',async()=>{
        if(!currentWatchlist){message('scan-message','Select a watchlist first.','error');return;}
        const profiles=[...document.querySelectorAll('input[name="profile"]:checked')].map(x=>x.value);
        if(!profiles.length){message('scan-message','Select at least one scan profile.','error');return;}
        $('run-scan').disabled=true;$('scan-results').replaceChildren();message('scan-message','Running scan. Candle synchronization and ICT analysis may take some time…');
        try{const data=await request('/egx/scan',{method:'POST',body:JSON.stringify({watchlist_id:currentWatchlist.id,profiles})});renderResults(data);message('scan-message','Scan completed.','success');}
        catch(e){message('scan-message',e.message,'error');}finally{$('run-scan').disabled=false;}
    });
    $('scan-history').addEventListener('click',async()=>{
        if(!currentWatchlist)return;
        try{const data=await request('/egx/watchlists/'+currentWatchlist.id+'/scans');const host=$('scan-results');host.replaceChildren();const pre=document.createElement('pre');pre.textContent=JSON.stringify(data,null,2);host.append(pre);}
        catch(e){message('scan-message',e.message,'error');}
    });

    refreshCapture();setInterval(refreshCapture,15000);
    loadStocks().catch(()=>{});loadWatchlists().catch(e=>message('sync-message',e.message,'error'));
})();
</script>
</body>
</html>