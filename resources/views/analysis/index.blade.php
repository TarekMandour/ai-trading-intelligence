<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Trading Intelligence</title>
</head>
<body>
    <h1>AI Trading Intelligence</h1>
    <p>Manual Spot-long research only. Scores are not probabilities; reports may contain no opportunity.</p>

    <section aria-labelledby="thndr-capture-heading">
        <h2 id="thndr-capture-heading">Thndr Token Capture</h2>
        <p>Status: <strong id="thndr-capture-state">Loading…</strong></p>
        <p>Captured token: <code id="thndr-token-masked">Not received</code></p>
        <p>Last updated: <span id="thndr-token-updated">—</span></p>
        <button type="button" id="thndr-capture-start">Start Capture</button>
        <button type="button" id="thndr-capture-stop" disabled>Stop Capture</button>
        <p id="thndr-capture-message" role="status" aria-live="polite"></p>
    </section>

    @if(session('status'))<p>{{ session('status') }}</p>@endif
    <form method="post" action="{{ route('analysis-runs.store') }}">@csrf<button>Start Analysis</button></form>
    <h2>Historical runs</h2>
    <ul>
        @forelse($runs as $run)
            <li><a href="{{ route('analysis-runs.show', $run) }}">Run #{{ $run->id }}</a> — {{ $run->status }} / AI: {{ $run->ai_status }}</li>
        @empty
            <li>No runs yet.</li>
        @endforelse
    </ul>
    {{ $runs->links() }}

    <script>
    (() => {
        const statusUrl = @json(url('/api/thndr/capture/status'));
        const startUrl = @json(url('/api/thndr/capture/start'));
        const stopUrl = @json(url('/api/thndr/capture/stop'));
        const state = document.getElementById('thndr-capture-state');
        const token = document.getElementById('thndr-token-masked');
        const updated = document.getElementById('thndr-token-updated');
        const message = document.getElementById('thndr-capture-message');
        const start = document.getElementById('thndr-capture-start');
        const stop = document.getElementById('thndr-capture-stop');

        function render(data) {
            const enabled = Boolean(data.enabled);
            state.textContent = enabled ? 'Running' : 'Stopped';
            start.disabled = enabled;
            stop.disabled = !enabled;
            token.textContent = data.tokenMasked || 'Not received';
            updated.textContent = data.tokenUpdatedAt || '—';
        }

        async function refresh() {
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Could not retrieve capture status.');
                render(await response.json());
            } catch (error) {
                message.textContent = error.message;
            }
        }

        async function setCapture(url, action) {
            start.disabled = true;
            stop.disabled = true;
            message.textContent = 'Please wait…';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { Accept: 'application/json' }
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Could not change capture state.');
                render(data);
                message.textContent = action === 'start' ? 'Capture enabled. Waiting for the extension to send a token…' : 'Capture stopped.';
                if (action === 'start') {
                    let attempts = 0;
                    const poll = setInterval(async () => {
                        attempts++;
                        await refresh();
                        if (attempts >= 10) clearInterval(poll);
                    }, 3000);
                }
            } catch (error) {
                message.textContent = error.message;
                await refresh();
            }
        }

        start.addEventListener('click', () => setCapture(startUrl, 'start'));
        stop.addEventListener('click', () => setCapture(stopUrl, 'stop'));
        refresh();
        setInterval(refresh, 15000);
    })();
    </script>
</body>
</html>
