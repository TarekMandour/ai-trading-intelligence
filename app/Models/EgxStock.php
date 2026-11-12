<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EgxStock extends Model
{
    protected $fillable = ['asset_id', 'symbol', 'name', 'arabic_name', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function watchlists(): BelongsToMany
    {
        return $this->belongsToMany(EgxWatchlist::class, 'egx_watchlist_stocks')->withPivot('position');
    }
}
