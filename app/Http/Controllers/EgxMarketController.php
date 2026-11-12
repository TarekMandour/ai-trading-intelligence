<?php

namespace App\Http\Controllers;

use App\Models\EgxStock;
use App\Models\EgxWatchlist;
use App\Models\MarketSetting;
use App\Services\MarketData\EgxCandleSyncService;
use App\Services\MarketData\EgxStockCatalog;
use App\Services\MarketData\ThndrApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EgxMarketController extends Controller
{
    public function captureToken(Request $request): JsonResponse
    {
        $key = (string) config('thndr.capture_key');
        abort_unless($key !== '' && hash_equals($key, (string) $request->header('X-Capture-Key')), 401);
        $data = $request->validate(['token' => ['required','string','max:4096']]);
        MarketSetting::updateOrCreate(['key' => 'thndr_api_token'], ['value' => $data['token']]);
        MarketSetting::updateOrCreate(['key' => 'thndr_token_updated_at'], ['value' => now()->toIso8601String()]);
        return response()->json(['ok' => true]);
    }

    public function syncUniverse(ThndrApiClient $api, EgxStockCatalog $catalog): JsonResponse
    {
        $count = $catalog->replaceFromMarketWatch($api->marketWatch());
        foreach ($catalog->all()['stocks'] ?? [] as $stock) {
            EgxStock::updateOrCreate(['asset_id' => $stock['asset_id']], [
                'symbol' => $stock['symbol'], 'name' => $stock['name'], 'arabic_name' => $stock['arabic_name'], 'metadata' => $stock['metadata'],
            ]);
        }
        return response()->json(['ok' => true, 'stocks_synced' => $count]);
    }

    public function watchlists(): JsonResponse
    {
        return response()->json(EgxWatchlist::with('stocks')->orderBy('name')->get());
    }

    public function scan(Request $request, ThndrApiClient $api, EgxCandleSyncService $sync): JsonResponse
    {
        $data = $request->validate([
            'watchlist_id' => ['required','integer','exists:egx_watchlists,id'],
            'timeframes' => ['sometimes','array','min:1','max:7'],
            'timeframes.*' => ['string','in:1m,5m,15m,1h,4h,1d,1w'],
        ]);
        $watchlist = EgxWatchlist::with('stocks')->findOrFail($data['watchlist_id']);
        abort_if($watchlist->stocks->isEmpty(), 422, 'WATCHLIST_EMPTY');
        $timeframes = $data['timeframes'] ?? ['1m','5m','15m','1h','4h','1d','1w'];
        $results = [];
        foreach ($watchlist->stocks as $stock) {
            $stockResult = ['asset_id' => $stock->asset_id, 'symbol' => $stock->symbol, 'sync' => [], 'candles' => []];
            foreach ($timeframes as $timeframe) {
                $stockResult['sync'][] = $sync->sync($stock->asset_id, $timeframe, $api);
                $stockResult['candles'][$timeframe] = $sync->candles($stock->asset_id, $timeframe);
            }
            $results[] = $stockResult;
        }
        return response()->json(['watchlist_id' => $watchlist->id, 'watchlist' => $watchlist->name, 'stocks' => $results]);
    }
}
