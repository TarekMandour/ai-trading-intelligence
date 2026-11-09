<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }
}
