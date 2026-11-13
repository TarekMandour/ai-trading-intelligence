<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgxScanRun extends Model
{
    protected $fillable = ['egx_watchlist_id', 'status', 'profiles', 'results', 'failure_reason', 'started_at', 'completed_at'];
    protected function casts(): array
    {
        return ['profiles' => 'array', 'results' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
    public function watchlist(): BelongsTo
    {
        return $this->belongsTo(EgxWatchlist::class, 'egx_watchlist_id');
    }
}
