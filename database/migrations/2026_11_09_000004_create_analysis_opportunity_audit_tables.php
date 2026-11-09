<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_opportunities', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('analysis_report_id')->constrained()->cascadeOnDelete();
            $t->string('symbol', 20)->nullable();
            $t->string('scenario', 64)->nullable();
            $t->string('decision', 20);
            $t->decimal('entry', 20, 8)->nullable();
            $t->decimal('stop', 20, 8)->nullable();
            $t->decimal('target_1', 20, 8)->nullable();
            $t->decimal('target_2', 20, 8)->nullable();
            $t->json('snapshot');
            $t->timestamps();
            $t->unique('analysis_report_id');
        });
        Schema::create('analysis_evidence', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('analysis_opportunity_id')->constrained()->cascadeOnDelete();
            $t->string('evidence_key', 64);
            $t->string('kind', 20);
            $t->json('payload');
            $t->timestamp('recorded_at');
            $t->unique(['analysis_opportunity_id', 'evidence_key']);
        });
        Schema::create('analysis_ai_reviews', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('analysis_opportunity_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 32)->nullable();
            $t->string('model', 100)->nullable();
            $t->string('status', 20);
            $t->string('prompt_version', 32)->default('phase-1');
            $t->unsignedInteger('latency_ms')->nullable();
            $t->json('review')->nullable();
            $t->timestamps();
            $t->unique('analysis_opportunity_id');
        });
        Schema::create('analysis_audit_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $t->foreignId('analysis_report_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('analysis_opportunity_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('event', 64);
            $t->json('payload');
            $t->timestamp('occurred_at');
            $t->index(['analysis_run_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_audit_events');
        Schema::dropIfExists('analysis_ai_reviews');
        Schema::dropIfExists('analysis_evidence');
        Schema::dropIfExists('analysis_opportunities');
    }
};
