<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('egx_scan_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('egx_watchlist_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->json('profiles');
            $table->json('results')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('egx_scan_runs'); }
};
