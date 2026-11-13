<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('egx_scan_runs', function (Blueprint $table): void {
            $table->string('ai_status', 24)->default('PENDING')->after('status');
            $table->string('ai_provider', 24)->nullable()->after('ai_status');
            $table->string('ai_model', 80)->nullable()->after('ai_provider');
        });
    }

    public function down(): void
    {
        Schema::table('egx_scan_runs', function (Blueprint $table): void {
            $table->dropColumn(['ai_status', 'ai_provider', 'ai_model']);
        });
    }
};
