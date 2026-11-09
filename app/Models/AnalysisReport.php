<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisReport extends Model
{
    protected $fillable = ['profile', 'symbol', 'status', 'timeframes', 'reasons', 'data_quality_warnings', 'ai_review'];

    protected function casts(): array
    {
        return ['timeframes' => 'array', 'reasons' => 'array', 'data_quality_warnings' => 'array', 'ai_review' => 'array'];
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function opportunity(): HasOne
    {
        return $this->hasOne(AnalysisOpportunity::class);
    }
}
