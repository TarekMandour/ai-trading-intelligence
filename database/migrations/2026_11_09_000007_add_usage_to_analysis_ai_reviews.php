<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_ai_reviews', function (Blueprint $table): void {
            $table->json('usage')->nullable()->after('latency_ms');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_ai_reviews', function (Blueprint $table): void {
            $table->dropColumn('usage');
        });
    }
};
