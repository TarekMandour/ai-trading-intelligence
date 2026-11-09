<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_audit_events', function (Blueprint $t): void {
            $t->string('fingerprint', 64)->nullable()->after('event');
            $t->unique(['analysis_run_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('analysis_audit_events', function (Blueprint $t): void {
            $t->dropUnique(['analysis_run_id', 'fingerprint']);
            $t->dropColumn('fingerprint');
        });
    }
};
