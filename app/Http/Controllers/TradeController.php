<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TradeController extends Controller
{
    /**
     * Display a listing of the resource with weekly and monthly reports.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $trades = $user->trades()
            ->orderBy('traded_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Ringkasan Keuangan Global
        $initialBalance = (float) ($user->initial_balance ?? 1000);
        $accountType = $user->account_type ?? 'CENT'; // 'CENT' or 'USD'

        $totalTrades = $trades->count();
        $winningTrades = $trades->where('pnl', '>', 0)->count();
        $losingTrades = $trades->where('pnl', '<', 0)->count();
        $breakevenTrades = $trades->where('pnl', '==', 0)->count();

        $totalPnl = (float) $trades->sum('pnl');
        $currentBalance = $initialBalance + $totalPnl;
        $growthPercentage = $initialBalance > 0 ? round(($totalPnl / $initialBalance) * 100, 2) : 0;
        $winRate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 1) : 0;

        $totalProfit = (float) $trades->where('pnl', '>', 0)->sum('pnl');
        $totalLoss = abs((float) $trades->where('pnl', '<', 0)->sum('pnl'));
        $profitFactor = $totalLoss > 0 ? round($totalProfit / $totalLoss, 2) : ($totalProfit > 0 ? $totalProfit : 0);

        // Laporan Mingguan
        $weeklyReports = $trades->groupBy(function ($trade) {
            $date = Carbon::parse($trade->traded_at);

            return $date->format('o-\WW'); // Format ISO-8601 Year and Week (e.g. 2026-W40)
        })->map(function ($weekTrades, $weekKey) {
            $sampleDate = Carbon::parse($weekTrades->first()->traded_at);
            $startOfWeek = $sampleDate->copy()->startOfWeek(Carbon::MONDAY);
            $endOfWeek = $sampleDate->copy()->endOfWeek(Carbon::SUNDAY);

            $wTotal = $weekTrades->count();
            $wWin = $weekTrades->where('pnl', '>', 0)->count();
            $wLoss = $weekTrades->where('pnl', '<', 0)->count();
            $wPnl = (float) $weekTrades->sum('pnl');
            $wWinRate = $wTotal > 0 ? round(($wWin / $wTotal) * 100, 1) : 0;

            return [
                'key' => $weekKey,
                'week_number' => $sampleDate->weekOfYear,
                'year' => $sampleDate->isoWeekYear,
                'label' => 'Minggu ke-'.$sampleDate->weekOfYear.' ('.$startOfWeek->format('d M').' - '.$endOfWeek->format('d M Y').')',
                'start_date' => $startOfWeek->format('Y-m-d'),
                'end_date' => $endOfWeek->format('Y-m-d'),
                'total_trades' => $wTotal,
                'win_trades' => $wWin,
                'loss_trades' => $wLoss,
                'total_pnl' => $wPnl,
                'win_rate' => $wWinRate,
                'trade_ids' => $weekTrades->pluck('id')->values(),
            ];
        })->values();

        // Laporan Bulanan
        $monthlyReports = $trades->groupBy(function ($trade) {
            return Carbon::parse($trade->traded_at)->format('Y-m');
        })->map(function ($monthTrades, $monthKey) {
            $sampleDate = Carbon::parse($monthTrades->first()->traded_at);

            $mTotal = $monthTrades->count();
            $mWin = $monthTrades->where('pnl', '>', 0)->count();
            $mLoss = $monthTrades->where('pnl', '<', 0)->count();
            $mPnl = (float) $monthTrades->sum('pnl');
            $mWinRate = $mTotal > 0 ? round(($mWin / $mTotal) * 100, 1) : 0;

            return [
                'key' => $monthKey,
                'label' => $sampleDate->isoFormat('MMMM Y'),
                'year' => $sampleDate->year,
                'month' => $sampleDate->month,
                'total_trades' => $mTotal,
                'win_trades' => $mWin,
                'loss_trades' => $mLoss,
                'total_pnl' => $mPnl,
                'win_rate' => $mWinRate,
                'trade_ids' => $monthTrades->pluck('id')->values(),
            ];
        })->values();

        return Inertia::render('Trades/Index', [
            'trades' => $trades->map(function ($trade) {
                return [
                    'id' => $trade->id,
                    'traded_at' => Carbon::parse($trade->traded_at)->format('Y-m-d\TH:i'),
                    'traded_at_formatted' => Carbon::parse($trade->traded_at)->format('d M Y, H:i'),
                    'pair' => $trade->pair,
                    'type' => $trade->type,
                    'lot_size' => (float) $trade->lot_size,
                    'pnl' => (float) $trade->pnl,
                    'notes' => $trade->notes,
                ];
            }),
            'stats' => [
                'account_type' => $accountType,
                'initial_balance' => $initialBalance,
                'total_pnl' => $totalPnl,
                'current_balance' => $currentBalance,
                'growth_percentage' => $growthPercentage,
                'total_trades' => $totalTrades,
                'winning_trades' => $winningTrades,
                'losing_trades' => $losingTrades,
                'breakeven_trades' => $breakevenTrades,
                'win_rate' => $winRate,
                'total_profit' => $totalProfit,
                'total_loss' => $totalLoss,
                'profit_factor' => $profitFactor,
            ],
            'weekly_reports' => $weeklyReports,
            'monthly_reports' => $monthlyReports,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'traded_at' => ['required', 'date'],
            'pair' => ['required', 'string', 'max:20'],
            'type' => ['required', 'in:BUY,SELL'],
            'lot_size' => ['required', 'numeric', 'min:0.001', 'max:1000'],
            'pnl' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['pair'] = strtoupper(trim($validated['pair']));
        $validated['pnl'] = (float) $validated['pnl'];

        $request->user()->trades()->create($validated);

        return redirect()->back()->with('success', 'Transaksi trading berhasil dicatat!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Trade $trade): RedirectResponse
    {
        if ($trade->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'traded_at' => ['required', 'date'],
            'pair' => ['required', 'string', 'max:20'],
            'type' => ['required', 'in:BUY,SELL'],
            'lot_size' => ['required', 'numeric', 'min:0.001', 'max:1000'],
            'pnl' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['pair'] = strtoupper(trim($validated['pair']));
        $validated['pnl'] = (float) $validated['pnl'];

        $trade->update($validated);

        return redirect()->back()->with('success', 'Transaksi trading berhasil diperbarui!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Trade $trade): RedirectResponse
    {
        if ($trade->user_id !== $request->user()->id) {
            abort(403);
        }

        $trade->delete();

        return redirect()->back()->with('success', 'Transaksi trading berhasil dihapus!');
    }

    /**
     * Update user's initial balance / starting modal.
     */
    public function updateInitialBalance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'initial_balance' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $request->user()->update([
            'initial_balance' => $validated['initial_balance'],
        ]);

        return redirect()->back()->with('success', 'Modal awal berhasil diperbarui!');
    }

    /**
     * Update account settings (Modal Awal & Mode Akun Cent / Standard).
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'initial_balance' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'account_type' => ['required', 'in:CENT,USD'],
        ]);

        $request->user()->update([
            'initial_balance' => $validated['initial_balance'],
            'account_type' => $validated['account_type'],
        ]);

        return redirect()->back()->with('success', 'Pengaturan akun berhasil disimpan!');
    }
}
