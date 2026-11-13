<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('egx_scan_runs', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'egx_scan_runs_created_at_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('egx_scan_runs', function (Blueprint $table): void {
            $table->dropIndex('egx_scan_runs_created_at_id_idx');
        });
    }
};
