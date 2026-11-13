<?php

namespace App\Http\Controllers;

use App\Models\EgxScanRun;
use App\Models\EgxStock;
use App\Models\EgxWatchlist;
use App\Models\MarketSetting;
use App\Services\Analysis\IctComprehensiveEngine;
use App\Services\MarketData\EgxCandleSyncService;
use App\Services\MarketData\EgxIctBacktestService;
use App\Services\MarketData\EgxStockCatalog;
use App\Services\MarketData\ThndrApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EgxMarketController extends Controller
{
    public function captureStatus(): JsonResponse
    {
        $token = (string) (MarketSetting::query()->where('key', 'thndr_api_token')->value('value') ?? '');

        return response()->json([
            'ok' => true,
            'enabled' => (string) (MarketSetting::query()->where('key', 'thndr_capture_enabled')->value('value') ?? '0') === '1',
            'tokenMasked' => $token === '' ? null : '••••••' . substr($token, -6),
            'tokenUpdatedAt' => MarketSetting::query()->where('key', 'thndr_token_updated_at')->value('value'),
        ]);
    }

    public function captureStart(): JsonResponse
    {
        return $this->setCaptureEnabled(true);
    }

    public function captureStop(): JsonResponse
    {
        return $this->setCaptureEnabled(false);
    }

    private function setCaptureEnabled(bool $enabled): JsonResponse
    {
        MarketSetting::updateOrCreate(
            ['key' => 'thndr_capture_enabled'],
            ['value' => $enabled ? '1' : '0'],
        );

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    public function captureToken(Request $request): JsonResponse
    {
        $key = (string) config('thndr.capture_key');
        abort_unless($key !== '' && hash_equals($key, (string) $request->header('X-Capture-Key')), 401);
        // Capture is accepted only while the dashboard toggle is enabled, matching doji_trade.
        $captureEnabled = (string) (MarketSetting::query()->where('key', 'thndr_capture_enabled')->value('value') ?? '0');
        abort_unless($captureEnabled === '1', 409, 'capture_disabled');

        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        MarketSetting::updateOrCreate(['key' => 'thndr_api_token'], ['value' => $data['token']]);
        MarketSetting::updateOrCreate(['key' => 'thndr_token_updated_at'], ['value' => now()->toIso8601String()]);
        return response()->json(['ok' => true]);
    }

    public function syncUniverse(ThndrApiClient $api, EgxStockCatalog $catalog): JsonResponse
    {
        $count = $catalog->replaceFromMarketWatch($api->marketWatch());
        foreach ($catalog->all()['stocks'] ?? [] as $stock) {
            EgxStock::updateOrCreate(['asset_id' => $stock['asset_id']], [
                'symbol' => $stock['symbol'], 'name' => $stock['name'],
                'arabic_name' => $stock['arabic_name'], 'metadata' => $stock['metadata'],
            ]);
        }
        return response()->json(['ok' => true, 'stocks_synced' => $count]);
    }

    public function stocks(Request $request): JsonResponse
    {
        $query = EgxStock::query()->orderBy('symbol');
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($builder) => $builder
                ->where('symbol', 'like', '%'.$search.'%')
                ->orWhere('name', 'like', '%'.$search.'%')
                ->orWhere('arabic_name', 'like', '%'.$search.'%'));
        }

        return response()->json($query->limit(1000)->get(['id', 'asset_id', 'symbol', 'name', 'arabic_name']));
    }

    public function watchlists(): JsonResponse
    {
        return response()->json(EgxWatchlist::with('stocks')->orderBy('name')->get());
    }

    public function createWatchlist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'asset_ids' => ['sometimes', 'array'],
            'asset_ids.*' => ['string', 'exists:egx_stocks,asset_id'],
        ]);
        $watchlist = EgxWatchlist::create(['name' => $data['name']]);
        $this->syncWatchlistStocks($watchlist, $data['asset_ids'] ?? []);
        return response()->json($watchlist->load('stocks'), 201);
    }

    public function updateWatchlist(Request $request, EgxWatchlist $watchlist): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'asset_ids' => ['sometimes', 'array'],
            'asset_ids.*' => ['string', 'exists:egx_stocks,asset_id'],
        ]);
        if (isset($data['name'])) $watchlist->update(['name' => $data['name']]);
        if (array_key_exists('asset_ids', $data)) $this->syncWatchlistStocks($watchlist, $data['asset_ids']);
        return response()->json($watchlist->load('stocks'));
    }

    private function syncWatchlistStocks(EgxWatchlist $watchlist, array $assetIds): void
    {
        $stocks = EgxStock::whereIn('asset_id', array_values(array_unique($assetIds)))->get()->keyBy('asset_id');
        $pivot = [];
        foreach (array_values(array_unique($assetIds)) as $position => $assetId) {
            if (isset($stocks[$assetId])) $pivot[$stocks[$assetId]->id] = ['position' => $position];
        }
        $watchlist->stocks()->sync($pivot);
    }

    public function scanHistory(EgxWatchlist $watchlist): JsonResponse
    {
        return response()->json(EgxScanRun::where('egx_watchlist_id', $watchlist->id)->latest()->paginate(20));
    }

    public function backtest(Request $request, EgxIctBacktestService $backtest): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'string', 'exists:egx_stocks,asset_id'],
            'profile' => ['required', 'string', 'in:Scalping,Intraday,Swing'],
            'fee_rate' => ['required', 'numeric', 'min:0', 'max:0.1'],
            'slippage_bps' => ['required', 'numeric', 'min:0', 'max:1000'],
            'initial_capital' => ['sometimes', 'numeric', 'gt:0'],
            'risk_fraction' => ['sometimes', 'numeric', 'gt:0', 'max:1'],
            'max_holding_bars' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'candle_limit' => ['sometimes', 'integer', 'min:100', 'max:10000'],
            'minimum_evaluation_bars' => ['sometimes', 'integer', 'min:5', 'max:1000'],
        ]);

        return response()->json($backtest->run(
            $data['asset_id'],
            $data['profile'],
            array_intersect_key($data, array_flip(['fee_rate', 'slippage_bps', 'initial_capital', 'risk_fraction', 'max_holding_bars', 'candle_limit', 'minimum_evaluation_bars'])),
        ));
    }

    public function scan(Request $request, ThndrApiClient $api, EgxCandleSyncService $sync, IctComprehensiveEngine $ict): JsonResponse
    {
        $data = $request->validate([
            'watchlist_id' => ['required', 'integer', 'exists:egx_watchlists,id'],
            'profiles' => ['sometimes', 'array', 'min:1', 'max:3'],
            'profiles.*' => ['string', 'in:Scalping,Intraday,Swing'],
        ]);
        $watchlist = EgxWatchlist::with('stocks')->findOrFail($data['watchlist_id']);
        abort_if($watchlist->stocks->isEmpty(), 422, 'WATCHLIST_EMPTY');

        $profiles = $data['profiles'] ?? ['Scalping', 'Intraday', 'Swing'];
        $profileFrames = [
            'Scalping' => ['context' => '1h', 'structure' => '15m', 'setup' => '5m', 'entry' => '1m'],
            'Intraday' => ['context' => '4h', 'structure' => '1h', 'setup' => '15m', 'entry' => '5m'],
            'Swing' => ['context' => '1w', 'structure' => '1d', 'setup' => '4h', 'entry' => '1h'],
        ];
        $run = EgxScanRun::create([
            'egx_watchlist_id' => $watchlist->id, 'status' => 'RUNNING',
            'profiles' => $profiles, 'started_at' => now(),
        ]);

        try {
            $results = [];
            foreach ($watchlist->stocks as $stock) {
                $stockResult = ['asset_id' => $stock->asset_id, 'symbol' => $stock->symbol, 'name' => $stock->name, 'arabic_name' => $stock->arabic_name, 'profiles' => []];
                foreach ($profiles as $profile) {
                    $series = [];
                    $syncReport = [];
                    $rejected = null;
                    foreach ($profileFrames[$profile] as $role => $timeframe) {
                        try {
                            $syncReport[$timeframe] = $sync->sync($stock->asset_id, $timeframe, $api);
                            $candles = $sync->candles($stock->asset_id, $timeframe, 500);
                            if (count($candles) < 30) throw new \RuntimeException('INSUFFICIENT_CANDLE_HISTORY:' . $timeframe);
                            $series[$role] = array_map(fn (array $c) => [
                                'open_time' => (int) $c['open_time'], 'close_time' => (int) $c['close_time'],
                                'open' => (float) $c['open'], 'high' => (float) $c['high'],
                                'low' => (float) $c['low'], 'close' => (float) $c['close'],
                                'volume' => (float) $c['volume'],
                            ], $candles);
                        } catch (Throwable $exception) {
                            $rejected = $exception->getMessage();
                            break;
                        }
                    }
                    if ($rejected) {
                        $profileResult = ['decision' => 'REJECTED', 'reasons' => [$rejected], 'sync' => $syncReport];
                    } else {
                        $profileResult = $ict->evaluate($series, $profile) + ['sync' => $syncReport];
                    }
                    // The ICT engine exposes its final outcome as `decision`; normalize it
                    // to `status` as well so API consumers have one consistent status field.
                    $profileResult['status'] = $profileResult['decision'] ?? 'UNKNOWN';
                    $stockResult['profiles'][$profile] = $profileResult;
                }
                $results[] = $stockResult;
            }
            $run->update(['status' => 'COMPLETED', 'results' => $results, 'completed_at' => now()]);
            return response()->json([
                'run_id' => $run->id, 'watchlist_id' => $watchlist->id,
                'watchlist' => $watchlist->name, 'market' => 'EGX', 'source' => 'thndr', 'stocks' => $results,
            ]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'FAILED', 'failure_reason' => class_basename($exception) . ': ' . $exception->getMessage(), 'completed_at' => now()]);
            throw $exception;
        }
    }
}
