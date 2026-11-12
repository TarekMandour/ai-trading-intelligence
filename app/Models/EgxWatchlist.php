<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EgxWatchlist extends Model
{
    protected $fillable = ['name', 'user_id'];

    public function stocks(): BelongsToMany
    {
        return $this->belongsToMany(EgxStock::class, 'egx_watchlist_stocks')
            ->withPivot('position')
            ->orderBy('egx_watchlist_stocks.position');
    }
}
