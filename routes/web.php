<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TradeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('trades.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/trades', [TradeController::class, 'index'])->name('trades.index');
    Route::post('/trades', [TradeController::class, 'store'])->name('trades.store');
    Route::put('/trades/{trade}', [TradeController::class, 'update'])->name('trades.update');
    Route::delete('/trades/{trade}', [TradeController::class, 'destroy'])->name('trades.destroy');
    Route::post('/trades/initial-balance', [TradeController::class, 'updateInitialBalance'])->name('trades.initial-balance');
    Route::post('/trades/settings', [TradeController::class, 'updateSettings'])->name('trades.settings');
});

require __DIR__.'/auth.php';
