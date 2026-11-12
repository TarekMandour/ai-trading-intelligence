<?php

namespace App\Services\MarketData;

use Illuminate\Support\Facades\File;
use RuntimeException;

class EgxStockCatalog
{
    private function path(): string { return storage_path('app/private/markets/egx/stocks.json'); }

    public function all(): array
    {
        $path = $this->path();
        if (!File::exists($path)) return [];
        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : [];
    }

    public function replaceFromMarketWatch(array $payload): int
    {
        $assets = $payload['assets'] ?? $payload['data'] ?? $payload['results'] ?? $payload;
        if (!is_array($assets)) throw new RuntimeException('THNDR_MARKETWATCH_FORMAT_INVALID');
        $stocks = [];
        foreach ($assets as $asset) {
            if (!is_array($asset)) continue;
            $id = $asset['id'] ?? $asset['asset_id'] ?? $asset['assetId'] ?? null;
            if (!is_string($id) && !is_numeric($id)) continue;

            $symbol = $asset['symbol'] ?? $asset['ticker'] ?? $asset['code'] ?? $asset['short_name'] ?? $asset['shortName'] ?? '';
            $name = $asset['name'] ?? $asset['english_name'] ?? $asset['company_name'] ?? $asset['display_name'] ?? $asset['englishName'] ?? '';
            $arabicName = $asset['arabic_name'] ?? $asset['name_ar'] ?? $asset['arabicName'] ?? '';

            $stocks[] = [
                'asset_id' => (string) $id,
                'symbol' => is_scalar($symbol) ? trim((string) $symbol) : '',
                'name' => is_scalar($name) ? trim((string) $name) : '',
                'arabic_name' => is_scalar($arabicName) ? trim((string) $arabicName) : '',
                'metadata' => $asset,
            ];
        }
        if ($stocks === []) throw new RuntimeException('THNDR_MARKETWATCH_EMPTY_OR_UNRECOGNIZED');
        File::ensureDirectoryExists(dirname($this->path()));
        $payload = json_encode(['updated_at' => now()->toIso8601String(), 'stocks' => $stocks], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tmp = $this->path().'.tmp';
        File::put($tmp, $payload);
        File::move($tmp, $this->path());
        return count($stocks);
    }

    public function find(string $assetId): ?array
    {
        foreach ($this->all()['stocks'] ?? [] as $stock) if (($stock['asset_id'] ?? null) === $assetId) return $stock;
        return null;
    }
}
