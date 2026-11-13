# Technical Strategy Replacement

## Objective
Use the strategy supplied in `StockAnalysisService(5).php` as the single deterministic technical-analysis engine for Binance Spot crypto and Thndr EGX stocks. The engine supports Scalping, Intraday and Swing profiles.

## Provider boundary
`TechnicalStrategyEngine` receives normalized OHLCV arrays grouped by profile role: context, structure, setup and entry. It contains no API client, credential, database or provider-specific logic. Binance and Thndr remain responsible for market-data acquisition and candle normalization.

## Strategy pipeline
1. Validate the four role series, ordering, OHLC geometry and completed-candle policy.
2. Find confirmed fractal swing points using a three-candle left/right window.
3. Classify HH/HL/LH/LL, BOS, CHOCH and liquidity sweeps.
4. Calculate trend classification and directional trend score.
5. Extract swing-based support/resistance, count touches, merge nearby levels and rank strength/proximity.
6. Calculate RVOL, volume trend, accumulation/distribution and volume score.
7. Calculate ATR and ATR percentage.
8. Build entry zone, stop, targets and risk/reward from support, resistance and ATR.
9. Calculate opportunity grade/score, speed score and fast-trade class.
10. Confirm support bounce and entry-timeframe breakout; require aligned context/structure/setup trends before ENTERABLE.

## Profiles
| Profile | Context | Structure | Setup | Entry |
|---|---|---|---|---|
| Scalping | 1h | 15m | 5m | 1m |
| Intraday | 4h | 1h | 15m | 5m |
| Swing | 1w | 1d | 4h | 1h |

## Correctness changes from the supplied source
- The source CHOCH counter did not encode direction and could add positive trend score for a bearish structural break. The replacement uses signed CHOCH: bearish transition is negative, bullish transition is positive.
- The source volume and ATR routines removed the last array element on the assumption that it was still forming. The replacement contract accepts completed candles only, so the most recent completed candle is included in these calculations.
- Candle timestamps are normalized for internal swing/level comparisons. Invalid or non-monotonic series are rejected instead of silently reordered.
- Multi-timeframe entry permission is gated by context, structure and setup trend alignment plus entry confirmation. Gemini remains independent and advisory.

## Decision contract
- `ENTERABLE`: setup qualifies, all three higher roles are bullish and support-bounce or entry-breakout confirmation is present.
- `WAIT`: a qualified candidate exists but alignment or entry confirmation is not complete.
- `NO_OPPORTUNITY`: the setup has no valid trade plan.
- `REJECTED`: required candle data is missing, malformed or insufficient.

## Backtest assumptions
The EGX replay uses the same strategy at historical decision timestamps and enters at the next entry candle open. It applies explicit per-side fee and slippage assumptions and resolves same-bar stop/target ambiguity stop-first. OHLCV simulation cannot reproduce queue priority, spread changes or historical order-book depth.

## Research limitations
Scores are ordinal ranking aids, not probabilities. Parameters have not been calibrated using untouched out-of-sample data, walk-forward validation or prospective paper trading. Do not infer profitability from example outputs or a successful code test.
