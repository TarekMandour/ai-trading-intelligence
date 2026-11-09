<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 20)->index();
            $table->string('configuration_version', 32);
            $table->string('ai_status', 20)->default('NOT_CONFIGURED');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('audit')->nullable();
            $table->timestamps();
        });

        Schema::create('analysis_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->string('profile', 20);
            $table->string('status', 20);
            $table->json('timeframes');
            $table->json('reasons');
            $table->json('data_quality_warnings')->nullable();
            $table->json('ai_review')->nullable();
            $table->timestamps();
            $table->unique(['analysis_run_id', 'profile']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_reports');
        Schema::dropIfExists('analysis_runs');
    }
};
