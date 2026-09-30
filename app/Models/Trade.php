<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'entry_price',
        'exit_price',
        'lot_size',
        'stop_loss',
        'take_profit',
        'pips',
        'pnl_usd',
        'session',
        'strategy_tag',
        'news_tag',
        'chart_img_path',
        'notes',
        'status',
    ];

    // Relasi ke User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Event listener bawaan Laravel untuk kalkulasi otomatis Pips & PnL
    protected static function booted(): void
    {
        static::saving(function (Trade $trade) {
            // Hanya hitung jika exit_price terisi (posisi CLOSED)
            if ($trade->exit_price && $trade->status === 'CLOSED') {
                $priceDiff = $trade->type === 'BUY'
                    ? $trade->exit_price - $trade->entry_price
                    : $trade->entry_price - $trade->exit_price;

                // 1 USD movement di XAU/USD = 10 Pips (atau 100 Points)
                $trade->pips = round($priceDiff * 10, 1);

                // PnL USD = Selisih Harga * (Lot * 100 oz)
                $trade->pnl_usd = round($priceDiff * ($trade->lot_size * 100), 2);
            }
        });
    }
}
