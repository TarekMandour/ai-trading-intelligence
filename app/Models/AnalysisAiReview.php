<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisAiReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['review' => 'array'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(AnalysisOpportunity::class, 'analysis_opportunity_id');
    }
}
