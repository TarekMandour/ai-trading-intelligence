<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_ai_reviews', function (Blueprint $table): void {
            $table->string('schema_version', 40)->nullable()->after('prompt_version');
            $table->char('evidence_hash', 64)->nullable()->after('schema_version');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_ai_reviews', function (Blueprint $table): void {
            $table->dropColumn(['schema_version', 'evidence_hash']);
        });
    }
};
