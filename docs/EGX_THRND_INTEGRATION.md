# EGX / Thndr Integration

This integration is additive. Binance remains the crypto provider; Binance and Thndr feed the same provider-neutral TechnicalStrategyEngine.

## Chrome extension token capture

The extension should POST the captured access token to:

`POST /api/thndr/token`

Headers:
- `Accept: application/json`
- `Content-Type: application/json`
- `X-Capture-Key: <same secret configured as THNDR_CAPTURE_KEY>`

Body:
```json
{"token":"<captured Thndr bearer token>"}
```

The endpoint stores the token in `market_settings` under `thndr_api_token`. The request must include `X-Capture-Key` matching `THNDR_CAPTURE_KEY`, and capture must be enabled by setting `thndr_capture_enabled=1` in `market_settings`, matching the legacy `doji_trade` controller behavior. Never commit the secret or token. The old Chrome extension source was not present in the doji_trade repository tree, so this endpoint preserves the observed server-side contract; only its destination URL should point to this application.

## Capture controls

The analysis dashboard includes Start Capture and Stop Capture controls, matching the legacy dashboard behavior. The controls call:

- `GET /api/thndr/capture/status`
- `POST /api/thndr/capture/start`
- `POST /api/thndr/capture/stop`

Starting sets `thndr_capture_enabled=1`; stopping sets it to `0`. The dashboard polls status every 15 seconds and, after Start, polls every 3 seconds for up to 30 seconds to detect a newly captured token. The token is displayed only in masked form. The capture endpoint continues to require the configured shared `X-Capture-Key`; the toggle alone does not authorize token submission.

## EGX universe and watchlists

- `POST /api/egx/universe/sync` fetches Thndr marketwatch and writes `storage/app/private/markets/egx/stocks.json`; it also refreshes the queryable `egx_stocks` table.
- `GET /api/egx/watchlists` lists watchlists and their stocks.
- `POST /api/egx/watchlists` accepts `{"name":"My list","asset_ids":["..."]}`.
- `PUT /api/egx/watchlists/{id}` updates the name and/or selected asset IDs.
- `POST /api/egx/scan` accepts a watchlist ID and optional profiles. Only stocks in that watchlist are scanned. Each run is persisted in `egx_scan_runs` with its watchlist ID, selected profiles and JSON result snapshot.
- `GET /api/egx/watchlists/{id}/scans` returns paginated scan history for that watchlist only.

## Historical candles

Native Thndr frames currently mapped from the legacy client are 1m, 5m, 10m, 1h, 1d and 1w. The 15m frame is aggregated from 5m; 4h is aggregated from 1h. Candles are persisted in `egx_candles` with a unique key on asset, timeframe and open timestamp. Each sync starts at the latest stored candle minus a small refresh overlap, so recent bars are corrected and old history is not downloaded again. Forming bars are excluded.

Important limitation: this first sync implementation repairs the newest tail and refreshes recent bars; it does not yet search for arbitrary historical holes inside an otherwise populated range. The EGX session-aware gap calendar and exact aggregation boundaries should be validated against live Thndr responses before relying on historical completeness.

## Security and validation

The token endpoint is protected by the shared capture key, requires `thndr_capture_enabled=1`, and is rate-limited. Do not expose `market_settings.value` in API responses or logs. The EGX scan/universe/watchlist endpoints are currently API endpoints without user authentication because the current MVP has no authentication flow. Do not expose them to the public internet; add authenticated dashboard middleware and user ownership checks before deployment. Token values are never returned by these endpoints.



## Shared technical strategy and historical EGX backtest (2026-11-13)

The EGX scan endpoint invokes `App\\Services\\Analysis\\TechnicalStrategyEngine`, the same provider-neutral strategy used by the Binance crypto queue. It consumes completed normalized OHLCV candles and returns the trend/structure analysis, support/resistance levels, volume evidence, ATR, entry zone, stop loss, targets, risk/reward, opportunity score, speed score and support-bounce confirmation. Gemini remains a separate advisory analysis and cannot replace the deterministic result.

### Historical backtest endpoint

`POST /api/egx/backtest` continues to require explicit `fee_rate` and `slippage_bps` assumptions and reads persisted completed Thndr candles. The replacement replay evaluates the same strategy at each historical decision time, enters no earlier than the next entry candle open, exits against stop/target/holding limit, and resolves same-bar stop/target ambiguity stop-first. Fees and slippage are explicit per-side assumptions. It reports trade count, wins/losses, win rate, net return and maximum drawdown.

### Validation caveats

- Historical OHLCV fills are modeled assumptions, not confirmed exchange fills or queue priority.
- Corporate actions, dividends, rights issues, symbol changes, historical order-book depth and full EGX session calendars are not modeled by this replay.
- The strategy score thresholds are research hypotheses, not calibrated win probabilities. A completed replay is not proof of profitability.
- Verify Thndr timestamp semantics, derived timeframe boundaries and market-specific fee/slippage assumptions before relying on results.
