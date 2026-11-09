<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisOpportunity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'entry' => 'decimal:8', 'stop' => 'decimal:8', 'target_1' => 'decimal:8', 'target_2' => 'decimal:8'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(AnalysisReport::class, 'analysis_report_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AnalysisEvidence::class);
    }

    public function aiReview(): HasOne
    {
        return $this->hasOne(AnalysisAiReview::class);
    }
}
