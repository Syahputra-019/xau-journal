<?php

namespace Database\Factories;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
class TradeFactory extends Factory
{
    protected $model = Trade::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['BUY', 'SELL']);

        return [
            'user_id' => User::factory(),
            'traded_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'pair' => 'XAUUSD',
            'type' => $type,
            'lot_size' => 0.1,
            'pnl' => fake()->randomFloat(2, -150, 300),
            'notes' => fake()->sentence(),
        ];
    }
}
