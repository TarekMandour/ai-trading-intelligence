# ICT Comprehensive Implementation

Status: Initial implementation slice on feature/ict-comprehensive. Thresholds are research hypotheses and are not validated trading parameters.

## Approved operating constraints

- Binance Spot USDT pairs.
- Long-only; no short entries, margin, leverage, or automated order execution.
- Scalping, Intraday, Swing profiles retain the existing timeframe map.
- Analyze 24 hours; no general kill-zone filter.
- Market and Limit are supported as planned entry methods; this application records research plans and does not submit exchange orders.
- Deterministic engine is authoritative; AI remains advisory.

## Engine contract

App\Services\Analysis\IctComprehensiveEngine::evaluate(array $candlesByRole, string $profile): array

Required roles: context, structure, setup, entry. Every series requires at least 30 strictly time-ordered, completed OHLCV candles with valid high/low geometry.

The engine returns context, setup evidence, entry evidence, three core scenario results, and a deterministic decision.

## Core models

- ICT-01 Sell-Side Liquidity Sweep Reversal.
- ICT-02 Bullish FVG Continuation.
- ICT-03 Bullish Order Block Reaction.

The first implementation uses deterministic pivot, liquidity, displacement, MSS, FVG, and OB heuristics. These are explicit baselines for review and calibration, not universal definitions of ICT and not claims of profitability.

## Limitations

- Current setup evidence uses the latest completed setup candle for some confirmations; production-quality event sequencing, evidence persistence, and replay-safe lifecycle transitions still require integration work.
- Equal highs/lows clustering, multi-candle displacement, zone touch history, FVG partial/full fill lifecycle, breaker/rejection blocks, OTE confluence, Silver Bullet/Judas session windows, and SMT reference assets are not integrated.
- Market/Limit are represented as research entry methods; no order placement is implemented or authorized.
- Historical fill accuracy cannot be inferred from OHLCV alone.
- No historical backtest or profitability validation has been performed.
