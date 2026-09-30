<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Parameter dasar transaksi
            $table->enum('type', ['BUY', 'SELL']);
            $table->decimal('entry_price', 8, 2); // Misal: 2650.50
            $table->decimal('exit_price', 8, 2)->nullable();
            $table->decimal('lot_size', 5, 2); // Misal: 0.10
            $table->decimal('stop_loss', 8, 2)->nullable();
            $table->decimal('take_profit', 8, 2)->nullable();
            
            // Hasil kalkulasi
            $table->decimal('pips', 8, 1)->nullable(); // Total Pips (+/-)
            $table->decimal('pnl_usd', 10, 2)->nullable(); // Profit/Rugi bersih (USD)
            
            // Karakteristik & Tagging XAU/USD
            $table->enum('session', ['ASIA', 'LONDON', 'NEW_YORK'])->nullable();
            $table->string('strategy_tag')->nullable(); // SMC, Breakout, dll.
            $table->string('news_tag')->nullable(); // CPI, NFP, FOMC, NO_NEWS
            $table->string('chart_img_path')->nullable(); // Path screenshot
            $table->text('notes')->nullable(); // Catatan evaluasi/psikologi
            
            $table->enum('status', ['OPEN', 'CLOSED'])->default('CLOSED');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
