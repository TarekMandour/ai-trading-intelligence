# EGX / Thndr Integration

This integration is additive. Binance remains the default crypto provider and the ICT engine is reused for EGX.

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

The endpoint stores the token in `market_settings` under `thndr_api_token`. Configure `THNDR_CAPTURE_KEY` in the server environment; never commit the secret or token. The old Chrome extension source was not present in the doji_trade repository tree, so this endpoint preserves the observed server-side contract but extension delivery still needs its original source/configuration.

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

The token endpoint is protected by the shared capture key and rate-limited. Do not expose `market_settings.value` in API responses or logs. The EGX scan/universe/watchlist endpoints are currently API endpoints without user authentication because the current MVP has no authentication flow. Do not expose them to the public internet; add authenticated dashboard middleware and user ownership checks before deployment. Token values are never returned by these endpoints.
