<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('market_settings', function (Blueprint $table): void {
            $table->id(); $table->string('key')->unique(); $table->text('value'); $table->timestamps();
        });
        Schema::create('egx_stocks', function (Blueprint $table): void {
            $table->id(); $table->string('asset_id')->unique(); $table->string('symbol')->index();
            $table->string('name')->nullable(); $table->string('arabic_name')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('egx_watchlists', function (Blueprint $table): void {
            $table->id(); $table->string('name', 120); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->timestamps();
        });
        Schema::create('egx_watchlist_stocks', function (Blueprint $table): void {
            $table->id(); $table->foreignId('egx_watchlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('egx_stock_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('position')->default(0);
            $table->unique(['egx_watchlist_id','egx_stock_id']);
        });
        Schema::create('egx_candles', function (Blueprint $table): void {
            $table->id(); $table->string('asset_id'); $table->string('timeframe', 8); $table->unsignedBigInteger('open_time');
            $table->unsignedBigInteger('close_time'); $table->decimal('open', 24, 10); $table->decimal('high', 24, 10);
            $table->decimal('low', 24, 10); $table->decimal('close', 24, 10); $table->decimal('volume', 30, 10)->default(0);
            $table->timestamps(); $table->unique(['asset_id','timeframe','open_time']); $table->index(['timeframe','open_time']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('egx_candles'); Schema::dropIfExists('egx_watchlist_stocks');
        Schema::dropIfExists('egx_watchlists'); Schema::dropIfExists('egx_stocks'); Schema::dropIfExists('market_settings');
    }
};
