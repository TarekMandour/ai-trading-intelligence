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
        .opportunity-summary{border:1px solid #cbd5e1;border-radius:10px;padding:14px;margin:12px 0;background:#fff}.opportunity-summary h5{margin:0 0 8px;font-size:15px}.opportunity-summary .watch{color:#1d4ed8;background:#dbeafe}.opportunity-summary .ready{color:#166534;background:#dcfce7}.opportunity-summary .metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(125px,1fr));gap:8px;margin:10px 0}.opportunity-summary .metric{background:#f3f4f6;padding:9px;border-radius:7px}.opportunity-summary .metric small{display:block;color:#667085}.opportunity-summary .metric strong{display:block;margin-top:4px}.opportunity-summary .disclaimer{font-size:12px;color:#667085}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#101828;color:#e6edf7;padding:14px;border-radius:8px;max-height:520px;overflow:auto}
        .message{min-height:22px;margin:10px 0;color:#475467}.success{color:#087443}.error{color:#b42318}.inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        @media(max-width:800px){body{padding:14px}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <h1>AI Trading Intelligence</h1>
    <p class="muted">EGX scanner powered by Thndr market data and the Technical Structure, Volume and Support/Resistance strategy.</p>
    <p><a href="{{ route('egx.watchlists.index') }}">Manage EGX Watchlists</a> · <a href="{{ route('egx.scans.index') }}">سجل عمليات الفحص والنتائج السابقة</a></p>

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
            <div><h2 style="margin-bottom:4px">EGX Market Scanner</h2><span class="muted">Sync the market, build a watchlist, then run the selected strategy profiles.</span></div>
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

    // Execute the provider-neutral technical strategy and render its evidence.
    function renderResults(data){
        const host=$('scan-results');host.replaceChildren();
        const heading=document.createElement('p');
        heading.textContent='Scan #'+data.run_id+' · '+(data.watchlist||'Watchlist')+' · '+(data.stocks?.length||0)+' stock(s)';
        host.append(heading);
        (data.stocks||[]).forEach(stock=>{
            const card=document.createElement('div');card.className='result-card';
            const title=document.createElement('h4');
            title.textContent=[stock.arabic_name||stock.name,stock.symbol].filter(Boolean).join(' — ')||'Stock';
            card.append(title);
            Object.entries(stock.profiles||{}).forEach(([profile,result])=>{
                const section=document.createElement('section');
                const line=document.createElement('p');
                line.textContent=profile+' · '+(result.status||result.decision||'UNKNOWN');
                section.append(line);
                const reasons=document.createElement('p');
                reasons.textContent='Reasons: '+((result.reasons||[]).join(', ')||'—');
                section.append(reasons);
                const candidate=result.candidate||{};
                const metrics=result.metrics||{};
                if(candidate.entry!==null && candidate.entry!==undefined){
                    const grid=document.createElement('div');grid.className='metric-grid';
                    const metric=(label,value)=>{const box=document.createElement('div');box.className='metric';const small=document.createElement('small');small.textContent=label;const strong=document.createElement('strong');strong.textContent=value??'—';box.append(small,strong);grid.append(box);};
                    metric('Entry',candidate.entry);metric('Entry zone',(candidate.entry_low??'—')+' – '+(candidate.entry_high??'—'));
                    metric('Stop loss',candidate.stop);metric('Target 1',candidate.target1);metric('Target 2',candidate.target2);
                    metric('Risk / Reward',candidate.risk_reward);metric('Opportunity score',metrics.opportunity_score);
                    metric('Speed score',metrics.speed_score);metric('Relative volume',metrics.relative_volume);
                    section.append(grid);
                }
                if(result.timeframe_alignment){
                    const align=document.createElement('p');
                    align.textContent='Trend · Context: '+result.timeframe_alignment.context+' · Structure: '+result.timeframe_alignment.structure+' · Setup: '+result.timeframe_alignment.setup+' · Aligned: '+(result.timeframe_alignment.aligned?'Yes':'No');
                    section.append(align);
                }
                if(result.levels){
                    const levels=document.createElement('p');
                    levels.textContent='Support levels: '+(result.levels.supports||[]).map(x=>x.price).join(', ')+' · Resistance levels: '+(result.levels.resistances||[]).map(x=>x.price).join(', ');
                    section.append(levels);
                }
                if(result.gemini_analysis){
                    const ai=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Gemini independent review';ai.append(summary);
                    const pre=document.createElement('pre');pre.textContent=JSON.stringify(result.gemini_analysis,null,2);ai.append(pre);section.append(ai);
                }
                if(result.sync){
                    const sync=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Candle synchronization';sync.append(summary);
                    const pre=document.createElement('pre');pre.textContent=JSON.stringify(result.sync,null,2);sync.append(pre);section.append(sync);
                }
                const evidence=document.createElement('details');const summary=document.createElement('summary');summary.textContent='Full technical strategy evidence';evidence.append(summary);
                const pre=document.createElement('pre');pre.textContent=JSON.stringify({metrics:result.metrics,alignment:result.timeframe_alignment,levels:result.levels,analysis:result.timeframe_analyses,raw:result.raw},null,2);
                evidence.append(pre);section.append(evidence);
                card.append(section);
            });
            host.append(card);
        });
        if(!(data.stocks||[]).length)host.textContent='No stock results returned.';
    }
    $('run-scan').addEventListener('click',async()=>{
        if(!currentWatchlist){message('scan-message','Select a watchlist first.','error');return;}
        const profiles=[...document.querySelectorAll('input[name="profile"]:checked')].map(x=>x.value);
        if(!profiles.length){message('scan-message','Select at least one scan profile.','error');return;}
        $('run-scan').disabled=true;$('scan-results').replaceChildren();message('scan-message','Running scan. Candle synchronization and technical analysis may take some time…');
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