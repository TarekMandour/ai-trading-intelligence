<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalysisRun extends Model
{
    use HasFactory;

    protected $fillable = ['idempotency_key', 'status', 'configuration_version', 'ai_status', 'started_at', 'completed_at', 'failure_reason', 'audit'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'audit' => 'array'];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AnalysisReport::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AnalysisAuditEvent::class);
    }
}
