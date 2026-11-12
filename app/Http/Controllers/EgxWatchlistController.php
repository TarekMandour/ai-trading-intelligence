<?php

namespace App\Http\Controllers;

use App\Models\EgxStock;
use App\Models\EgxWatchlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EgxWatchlistController extends Controller
{
    public function index(Request $request): View
    {
        $watchlists = $this->ownedWatchlists($request)
            ->withCount('stocks')
            ->latest()
            ->paginate(20);

        return view('egx.watchlists.index', compact('watchlists'));
    }

    public function create(): View
    {
        $watchlist = new EgxWatchlist();
        $stocks = EgxStock::query()->orderBy('symbol')->get(['id', 'symbol', 'name', 'arabic_name']);

        return view('egx.watchlists.form', compact('watchlist', 'stocks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $watchlist = EgxWatchlist::create([
            'name' => $data['name'],
            'user_id' => $request->user()?->id,
        ]);
        $this->syncStocks($watchlist, $data['stock_ids'] ?? []);

        return redirect()->route('egx.watchlists.show', $watchlist)->with('status', 'Watchlist created successfully.');
    }

    public function show(Request $request, int $watchlist): View
    {
        $watchlist = $this->ownedWatchlists($request)
            ->with('stocks')
            ->findOrFail($watchlist);

        return view('egx.watchlists.show', compact('watchlist'));
    }

    public function edit(Request $request, int $watchlist): View
    {
        $watchlist = $this->ownedWatchlists($request)
            ->with('stocks:id')
            ->findOrFail($watchlist);
        $selectedStockIds = $watchlist->stocks->modelKeys();
        $stocks = EgxStock::query()->orderBy('symbol')->get(['id', 'symbol', 'name', 'arabic_name']);

        return view('egx.watchlists.form', compact('watchlist', 'stocks', 'selectedStockIds'));
    }

    public function update(Request $request, int $watchlist): RedirectResponse
    {
        $watchlist = $this->ownedWatchlists($request)->findOrFail($watchlist);
        $data = $this->validatedData($request);
        $watchlist->update(['name' => $data['name']]);
        $this->syncStocks($watchlist, $data['stock_ids'] ?? []);

        return redirect()->route('egx.watchlists.show', $watchlist)->with('status', 'Watchlist updated successfully.');
    }

    public function destroy(Request $request, int $watchlist): RedirectResponse
    {
        $this->ownedWatchlists($request)->findOrFail($watchlist)->delete();

        return redirect()->route('egx.watchlists.index')->with('status', 'Watchlist deleted successfully.');
    }

    private function ownedWatchlists(Request $request)
    {
        return EgxWatchlist::query()->where('user_id', $request->user()?->id);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'stock_ids' => ['sometimes', 'array'],
            'stock_ids.*' => ['integer', 'distinct', 'exists:egx_stocks,id'],
        ]);
    }

    private function syncStocks(EgxWatchlist $watchlist, array $stockIds): void
    {
        $pivot = [];
        foreach (array_values(array_unique($stockIds)) as $position => $stockId) {
            $pivot[(int) $stockId] = ['position' => $position];
        }

        $watchlist->stocks()->sync($pivot);
    }
}
