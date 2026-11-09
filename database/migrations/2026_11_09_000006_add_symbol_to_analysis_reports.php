<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table): void {
            // Keep a dedicated index for the foreign key before replacing
            // the composite unique index that currently supports it.
            $table->index('analysis_run_id');
            $table->dropUnique(['analysis_run_id', 'profile']);
            $table->string('symbol', 20)->default('LEGACY')->after('profile');
            $table->unique(['analysis_run_id', 'profile', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table): void {
            $table->dropUnique(['analysis_run_id', 'profile', 'symbol']);
            $table->dropColumn('symbol');
            $table->unique(['analysis_run_id', 'profile']);
            $table->dropIndex(['analysis_run_id']);
        });
    }
};
