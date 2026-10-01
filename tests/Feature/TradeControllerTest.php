<?php

namespace Tests\Feature;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_trades_index(): void
    {
        $response = $this->get(route('trades.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_trades_index(): void
    {
        $user = User::factory()->create([
            'initial_balance' => 2000.00,
        ]);

        Trade::factory()->count(3)->create([
            'user_id' => $user->id,
            'pnl' => 50.00,
        ]);

        $response = $this->actingAs($user)->get(route('trades.index'));

        $response->assertOk();
    }

    public function test_user_can_create_a_trade(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('trades.store'), [
            'traded_at' => now()->format('Y-m-d H:i:s'),
            'pair' => 'XAUUSD',
            'type' => 'BUY',
            'lot_size' => 0.05,
            'pnl' => 25.00,
            'notes' => 'Test buy setup',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('trades', [
            'user_id' => $user->id,
            'pair' => 'XAUUSD',
            'type' => 'BUY',
            'lot_size' => 0.05,
            'pnl' => 25.00,
            'notes' => 'Test buy setup',
        ]);
    }

    public function test_user_can_update_their_trade(): void
    {
        $user = User::factory()->create();
        $trade = Trade::factory()->create([
            'user_id' => $user->id,
            'pnl' => 10.00,
        ]);

        $response = $this->actingAs($user)->put(route('trades.update', $trade), [
            'traded_at' => now()->format('Y-m-d H:i:s'),
            'pair' => 'XAUUSD',
            'type' => 'SELL',
            'lot_size' => 0.1,
            'pnl' => 100.00,
            'notes' => 'Updated to sell setup',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('trades', [
            'id' => $trade->id,
            'type' => 'SELL',
            'lot_size' => 0.1,
            'pnl' => 100.00,
            'notes' => 'Updated to sell setup',
        ]);
    }

    public function test_user_can_delete_their_trade(): void
    {
        $user = User::factory()->create();
        $trade = Trade::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('trades.destroy', $trade));

        $response->assertRedirect();
        $this->assertDatabaseMissing('trades', ['id' => $trade->id]);
    }

    public function test_user_cannot_delete_another_users_trade(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tradeB = Trade::factory()->create([
            'user_id' => $userB->id,
        ]);

        $response = $this->actingAs($userA)->delete(route('trades.destroy', $tradeB));

        $response->assertForbidden();
        $this->assertDatabaseHas('trades', ['id' => $tradeB->id]);
    }

    public function test_user_can_update_initial_balance(): void
    {
        $user = User::factory()->create([
            'initial_balance' => 500.00,
        ]);

        $response = $this->actingAs($user)->post(route('trades.initial-balance'), [
            'initial_balance' => 3000.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'initial_balance' => 3000.00,
        ]);
    }

    public function test_user_can_update_settings(): void
    {
        $user = User::factory()->create([
            'initial_balance' => 500.00,
            'account_type' => 'USD',
        ]);

        $response = $this->actingAs($user)->post(route('trades.settings'), [
            'initial_balance' => 5000.00,
            'account_type' => 'CENT',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'initial_balance' => 5000.00,
            'account_type' => 'CENT',
        ]);
    }
}
