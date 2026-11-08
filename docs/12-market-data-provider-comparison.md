# Crypto Spot Market-Data Provider Comparison

- Review date: 2026-11-09
- Purpose: Phase 0 vendor shortlist, not a final vendor approval.
- Product: centralized-exchange crypto spot scalping, long-only alerts.
- Commercial rights: must be confirmed in writing for the exact SaaS workflow.

## Executive recommendation
Use **Binance Spot public market data as the initial technical benchmark/prototype candidate**, but do not treat it as commercially approved until Binance confirms in writing that the intended collection, storage, derived analytics, and subscriber alert distribution are permitted for the company and target jurisdictions.

In parallel, request a commercial quote and rights confirmation from **CoinAPI** as the most practical paid multi-exchange alternative. Keep **Kaiko** as the institutional-grade option if the product later needs licensed multi-venue data, deep historical order books, formal redistribution rights, or stronger data governance.

Do not use CoinGecko as the primary scalping execution feed. It is more appropriate for asset metadata, broad market context, and reference/market-wide metrics.

## Comparison

| Source | Coverage and data | Real-time suitability | Historical/replay | Public pricing observed | Commercial rights posture |
|---|---|---|---|---|---|
| Binance Spot API (direct) | One venue; spot trades, quotes, depth, klines, exchange info; public REST and WebSocket | Strong venue-native feed; WebSocket streaming and REST snapshots; sequence-aware depth reconstruction required | REST candles/recent trades; downloadable public trade history is available through Binance data portal | Public market-data endpoints are available without authentication; API usage subject to rate limits. No paid market-data price identified in reviewed docs | **Unconfirmed for SaaS redistribution/derived alerts**. Obtain written permission/terms interpretation before commercial launch |
| CoinAPI | Aggregates 400+ exchanges; spot and derivatives; trades, quotes, L1/L2/L3 books, OHLCV, metadata; REST/WS/FIX/flat files | Strong product breadth; plan gates data types/quotas | Advertises 10+ years of tick history and historical flat files | Pay-as-you-go metered with $25 starter credits; Startup $79/mo; Streamer $249/mo; Pro $599/mo; Enterprise quote. Annual displayed rates differ | Product is aimed at commercial/institutional data use, but exact display, derived data, subscriber distribution, retention and redistribution rights must be stated in the order form/license |
| Kaiko | Institutional multi-venue coverage; spot trades, quotes, order books, OHLCV, market depth, slippage; broad exchange coverage | Institutional streams, tick-level data; product tiers and delivery channels vary | Extensive tick history; docs describe history since 2010 for some datasets and 72-hour live-stream replay for selected products | Enterprise/custom quote; no reliable public self-serve price found | Strongest visible licensing posture: Kaiko states it has formal redistribution agreements with listed exchanges. Customer's own permitted downstream use still needs contract confirmation |
| Coinbase Exchange API | One venue; spot products, WebSocket trades, ticker, Level 2 order book, REST market data | Strong real-time WS; heartbeat, ticker, matches and L2 channels documented | REST candles/trades available with endpoint-specific limits; historical depth replay should be validated | Public API docs; no general commercial data subscription price identified in reviewed material | **Important restriction:** Coinbase Market Data Terms prohibit redistribution/display to third parties and dissemination of derived works without prior express written consent |
| Kraken API | One venue; spot markets, REST and WebSocket, order book/trades/OHLC | Real-time public WebSocket; Kraken also describes L1/L2/L3 feeds and microsecond timestamps in product material | OHLC/trade history; verify exact historical tick/depth availability for target pairs | API access described publicly; no paid market-data quote identified | **Prior permission required for certain non-personal commercial use** of public API data; obtain written permission |


| CoinGecko API | Aggregated global prices, asset metadata, market cap/volume, historical market charts, plus WebSocket market streams; not a venue-native order book/trade execution feed | Useful for broad context and metadata; aggregated prices are not a substitute for the exact exchange's bid/ask/depth when scalping | Historical price/market metrics; plan-dependent history depth. The pricing page lists 5-minute historical data depth as limited by plan | Demo $0; Basic $35/mo; Analyst $129/mo; Lite $499/mo; Pro $999/mo (monthly displayed prices; annual discounts shown on pricing page); Enterprise quote | Paid self-serve plans include commercial use/display with attribution, but redistribution/syndication and reusable data access require custom rights. It is not suitable as the sole execution feed |

## Evidence and source links
- Binance API overview and REST/WebSocket capabilities: https://developers.binance.com/en/docs/introduction
- Binance Spot REST reference: https://developers.binance.com/en/docs/products/spot/rest-api
- Binance market-data-only REST/WebSocket endpoints: https://github.com/binance/binance-spot-api-docs/blob/master/faqs/market_data_only.md
- Binance Spot terms entry point: https://developers.binance.com/en/docs/products/spot/PROD-TERMS-OF-USE
- CoinAPI coverage and pricing: https://www.coinapi.io/
- CoinAPI pricing detail: https://www.coinapi.io/products/market-data-api/pricing
- Kaiko exchange licensing: https://www.kaiko.com/exchange-agreements
- Kaiko data dictionary: https://docs.kaiko.com/explore-our-data/data-dictionary
- Coinbase Market Data Terms: https://www.coinbase.com/legal/market_data
- Coinbase WebSocket feed details: https://help.coinbase.com/en/developer-platform/websocket-feeds/exchange
- Kraken API product capabilities: https://www.kraken.com/pro/trading-api
- Kraken API guide / commercial use notice: https://docs-legacy.kraken.com/api/docs/guides/global-intro/
- CoinGecko API pricing and plan limits: https://www.coingecko.com/en/api/pricing
- CoinGecko commercial vs custom licensing: https://support.coingecko.com/articles/1043089972-what-are-the-differences-between-commercial-and-custom-licenses

## Technical assessment for this MVP

### Minimum viable source capabilities
A candidate must deliver:
1. Spot instrument metadata and active trading status.
2. Real-time trades or aggregated trades.
3. Best bid/ask updates.
4. Order-book snapshot plus incremental updates, with a reliable resynchronization method.
5. 1m, 5m, 15m and 1h candles, or enough raw data to build them consistently.
6. Historical candles and preferably historical trades for event replay.
7. Fee schedule and instrument precision/lot constraints.
8. Documented rate limits, WebSocket limits, maintenance policy and outage behavior.

### Recommended data architecture
- Use the selected exchange's native feed as the authoritative source for its own execution venue.
- Use CoinAPI or Kaiko only if multi-venue normalization, historical tick data, or licensed distribution provides sufficient value to justify the cost.
- Do not merge order books from different venues into a synthetic book unless the strategy explicitly trades a multi-venue consolidated market.
- Store provider timestamps and local receive timestamps separately.
- Keep market-data ingestion separate from the opportunity engine so a provider can be replaced.

## Commercial/legal due-diligence checklist
Before production or paid subscriber distribution, obtain written answers to:
- May we collect and store real-time and historical data server-side?
- May we compute indicators, features, scores, and trade signals from it?
- May we show derived signals and selected market values to paying subscribers?
- May alerts contain symbol, last price, entry zone, stop, targets, spread, volume, or order-book-derived metrics?
- Are raw data, charts, derived data, or downloadable records restricted?
- What attribution is required?
- What caching/retention period is allowed, and what happens after termination?
- Are there subscriber, geographic, user-count, or display limits?
- Are downstream AI processors/cloud infrastructure permitted?
- Is the intended company jurisdiction and subscriber geography supported?
- What fees apply for professional display, redistribution, derived data, or enterprise use?

## Current shortlist status
- Binance Spot direct: technical benchmark candidate; commercial rights pending.
- CoinAPI: paid commercial alternative; exact downstream rights pending quote/contract.
- Kaiko: institutional alternative; quote and scope pending.
- Coinbase: not preferred for this use case until written consent resolves the broad redistribution/derived-work restrictions.
- Kraken: secondary candidate; written permission required for non-personal commercial use.
- CoinGecko: metadata/reference enrichment only, not execution-grade scalping feed. Paid commercial plans allow customer-facing display with attribution, but redistribution/syndication requires a custom license.

## Decision gate
Do not select a production provider based only on API latency, coverage, or sticker price. Provider approval requires a successful technical feed test, historical replay assessment, fee/slippage validation, written licensing clearance, and confirmation of venue availability for the intended operating company and subscriber jurisdictions.


##  Current MVP decision (supersedes shortlist recommendation)
- **Selected technical reference venue:** Binance Spot direct API.
- **Selected universe policy:** `top_liquid` dynamic eligible Spot pairs.
- CoinAPI and Kaiko remain alternatives for a future licensed multi-venue or historical-data expansion; they are not MVP primary feeds.
- The selection does not close the legal/commercial gate. Do not launch paid subscriber display or derived-signal distribution until the intended use is expressly permitted in writing and the operating/subscriber jurisdictions are cleared.
- The operational selection policy and provisional thresholds are defined in `docs/13-top-liquid-universe-and-latency.md`.
