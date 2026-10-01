<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    trades: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            account_type: 'CENT', // 'CENT' | 'USD'
            initial_balance: 1000,
            total_pnl: 0,
            current_balance: 1000,
            growth_percentage: 0,
            total_trades: 0,
            winning_trades: 0,
            losing_trades: 0,
            breakeven_trades: 0,
            win_rate: 0,
            total_profit: 0,
            total_loss: 0,
            profit_factor: 0,
        }),
    },
    weekly_reports: {
        type: Array,
        default: () => [],
    },
    monthly_reports: {
        type: Array,
        default: () => [],
    },
});

// Tab state: 'trades' | 'weekly' | 'monthly'
const activeTab = ref('trades');

// Filter state
const searchQuery = ref('');
const filterType = ref('ALL'); // ALL, BUY, SELL
const filterOutcome = ref('ALL'); // ALL, WIN, LOSS
const selectedWeekFilter = ref('');
const selectedMonthFilter = ref('');

// Modal states
const showTradeModal = ref(false);
const isEditing = ref(false);
const editingTradeId = ref(null);
const showSettingsModal = ref(false);

// Account Cent vs USD mode helper
const isCentMode = computed(() => props.stats.account_type === 'CENT');
const currencyUnit = computed(() => isCentMode.value ? 'USC' : 'USD');

// Form for Trade (Create / Edit)
const tradeForm = useForm({
    traded_at: '',
    pair: 'XAUUSD',
    type: 'BUY',
    lot_size: 0.01,
    pnl: '',
    notes: '',
});

// Form for Account Settings (Modal Awal & Account Type)
const settingsForm = useForm({
    initial_balance: props.stats.initial_balance,
    account_type: props.stats.account_type || 'CENT',
});

// Format currency
const formatCurrency = (val, showConversion = false) => {
    const num = Number(val) || 0;
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);

    if (isCentMode.value) {
        if (showConversion) {
            const inUSD = (num / 100).toFixed(2);
            return `${formatted} USC (≈ $${inUSD})`;
        }
        return `${formatted} USC`;
    }

    return `$${formatted}`;
};

// Current local ISO string for datetime-local input
const getCurrentLocalDatetime = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

// Open Create Modal
const openCreateModal = () => {
    isEditing.value = false;
    editingTradeId.value = null;
    tradeForm.reset();
    tradeForm.clearErrors();
    tradeForm.traded_at = getCurrentLocalDatetime();
    tradeForm.pair = 'XAUUSD';
    tradeForm.type = 'BUY';
    tradeForm.lot_size = 0.01;
    tradeForm.pnl = '';
    tradeForm.notes = '';
    showTradeModal.value = true;
};

// Open Edit Modal
const openEditModal = (trade) => {
    isEditing.value = true;
    editingTradeId.value = trade.id;
    tradeForm.clearErrors();
    tradeForm.traded_at = trade.traded_at;
    tradeForm.pair = trade.pair;
    tradeForm.type = trade.type;
    tradeForm.lot_size = trade.lot_size;
    tradeForm.pnl = trade.pnl !== null ? trade.pnl : '';
    tradeForm.notes = trade.notes || '';
    showTradeModal.value = true;
};

// Submit Trade Form
const submitTrade = () => {
    if (isEditing.value && editingTradeId.value) {
        tradeForm.put(route('trades.update', editingTradeId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showTradeModal.value = false;
                tradeForm.reset();
            },
        });
    } else {
        tradeForm.post(route('trades.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showTradeModal.value = false;
                tradeForm.reset();
            },
        });
    }
};

// Delete Trade
const deleteTrade = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus catatan transaksi ini?')) {
        router.delete(route('trades.destroy', id), {
            preserveScroll: true,
        });
    }
};

// Quick toggle mode
const toggleAccountType = () => {
    const nextType = isCentMode.value ? 'USD' : 'CENT';
    router.post(route('trades.settings'), {
        initial_balance: props.stats.initial_balance,
        account_type: nextType,
    }, {
        preserveScroll: true,
    });
};

// Open settings modal and sync state
const openSettingsModal = () => {
    settingsForm.initial_balance = props.stats.initial_balance;
    settingsForm.account_type = props.stats.account_type || 'CENT';
    showSettingsModal.value = true;
};

// Submit Settings
const submitSettings = () => {
    settingsForm.post(route('trades.settings'), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsModal.value = false;
        },
    });
};

// Filtered Trades
const filteredTrades = computed(() => {
    return props.trades.filter((t) => {
        // Search
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const matchPair = t.pair.toLowerCase().includes(q);
            const matchNotes = (t.notes || '').toLowerCase().includes(q);
            if (!matchPair && !matchNotes) return false;
        }
        // Type filter
        if (filterType.value !== 'ALL' && t.type !== filterType.value) {
            return false;
        }
        // Outcome filter
        if (filterOutcome.value === 'WIN' && t.pnl <= 0) return false;
        if (filterOutcome.value === 'LOSS' && t.pnl >= 0) return false;

        // Week filter
        if (selectedWeekFilter.value) {
            const weekObj = props.weekly_reports.find(w => w.key === selectedWeekFilter.value);
            if (weekObj && !weekObj.trade_ids.includes(t.id)) {
                return false;
            }
        }

        // Month filter
        if (selectedMonthFilter.value) {
            const monthObj = props.monthly_reports.find(m => m.key === selectedMonthFilter.value);
            if (monthObj && !monthObj.trade_ids.includes(t.id)) {
                return false;
            }
        }

        return true;
    });
});

const filterByWeek = (weekKey) => {
    selectedWeekFilter.value = weekKey;
    selectedMonthFilter.value = '';
    activeTab.value = 'trades';
};

const filterByMonth = (monthKey) => {
    selectedMonthFilter.value = monthKey;
    selectedWeekFilter.value = '';
    activeTab.value = 'trades';
};

const clearPeriodFilter = () => {
    selectedWeekFilter.value = '';
    selectedMonthFilter.value = '';
};
</script>

<template>
    <Head title="Jurnal Trading Pribadi" />

    <AuthenticatedLayout>
        <div class="space-y-4 sm:space-y-6 pb-20 sm:pb-8">
            <!-- Top Header & Action -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-100 flex items-center gap-2">
                        <span class="inline-block w-2 sm:w-2.5 h-5 sm:h-6 bg-cyan-400 rounded-sm"></span>
                        Jurnal Trading XAU/USD
                    </h1>

                    <!-- Quick Mode Toggle Button -->
                    <button
                        @click="toggleAccountType"
                        type="button"
                        title="Klik untuk beralih mode Akun Cent / Standar"
                        class="text-[10px] sm:text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-sm border transition flex items-center gap-1.5 active:scale-95"
                        :class="isCentMode ? 'bg-amber-950/70 text-amber-300 border-amber-800/70 hover:bg-amber-900/80' : 'bg-sky-950/70 text-sky-300 border-sky-800/70 hover:bg-sky-900/80'"
                    >
                        <span>{{ isCentMode ? '🪙 CENT' : '💵 USD' }}</span>
                        <span class="text-[9px] text-slate-400">⇄ Ganti</span>
                    </button>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <p class="truncate">Pencatatan simpel mingguan & bulanan</p>
                    <span class="text-slate-500 font-mono hidden sm:inline">Mode: {{ isCentMode ? 'Cent Account (USC)' : 'Standard USD' }}</span>
                </div>

                <!-- Action Buttons: Full width on mobile -->
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:justify-end">
                    <button
                        @click="openSettingsModal"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 sm:py-2 text-xs font-semibold text-slate-300 bg-slate-900 border border-slate-700/80 hover:bg-slate-800 hover:text-white rounded-sm transition shadow-sm active:bg-slate-800"
                    >
                        <svg class="w-4 h-4 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Atur Modal</span>
                    </button>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 sm:py-2 text-xs font-semibold text-slate-950 bg-gradient-to-r from-cyan-400 to-sky-400 hover:from-cyan-300 hover:to-sky-300 rounded-sm transition shadow-sm active:scale-98"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Catat Trade</span>
                    </button>
                </div>
            </div>

            <!-- Financial & Performance Overview Cards (2x2 Grid on mobile for instant visibility) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
                <!-- Modal Awal -->
                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-sm relative overflow-hidden group">
                    <div class="flex items-center justify-between text-[11px] sm:text-xs text-slate-400 font-medium mb-1">
                        <span class="truncate">Modal Awal</span>
                        <span class="text-[9px] sm:text-[10px] uppercase font-bold text-cyan-400/90 bg-cyan-950/60 px-1 py-0.2 rounded-sm border border-cyan-800/40">{{ currencyUnit }}</span>
                    </div>
                    <div class="text-base sm:text-2xl font-bold text-slate-100 font-mono tracking-tight">
                        {{ formatCurrency(stats.initial_balance) }}
                    </div>
                    <div class="mt-1 text-[10px] sm:text-[11px] text-slate-500 truncate">
                        {{ isCentMode ? `≈ $${(stats.initial_balance / 100).toFixed(2)} USD` : 'Mata uang USD' }}
                    </div>
                </div>

                <!-- Total Untung / Rugi -->
                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-sm relative overflow-hidden group">
                    <div class="flex items-center justify-between text-[11px] sm:text-xs text-slate-400 font-medium mb-1">
                        <span class="truncate">Total PnL</span>
                        <span
                            class="text-[9px] sm:text-[10px] font-bold px-1 py-0.2 rounded-sm border font-mono"
                            :class="stats.growth_percentage >= 0 ? 'bg-teal-950/60 text-teal-400 border-teal-800/40' : 'bg-rose-950/60 text-rose-400 border-rose-800/40'"
                        >
                            {{ stats.growth_percentage >= 0 ? '+' : '' }}{{ stats.growth_percentage }}%
                        </span>
                    </div>
                    <div
                        class="text-base sm:text-2xl font-bold font-mono tracking-tight"
                        :class="stats.total_pnl >= 0 ? 'text-teal-400' : 'text-rose-400'"
                    >
                        {{ stats.total_pnl >= 0 ? '+' : '' }}{{ formatCurrency(stats.total_pnl) }}
                    </div>
                    <div class="mt-1 text-[10px] sm:text-[11px] text-slate-500 flex items-center justify-between truncate">
                        <span class="text-teal-400/80">+{{ formatCurrency(stats.total_profit) }}</span>
                        <span class="text-rose-400/80">-{{ formatCurrency(stats.total_loss) }}</span>
                    </div>
                </div>

                <!-- Saldo Saat Ini -->
                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-sm relative overflow-hidden group">
                    <div class="flex items-center justify-between text-[11px] sm:text-xs text-slate-400 font-medium mb-1">
                        <span class="truncate">Saldo Akun</span>
                        <span class="text-[9px] sm:text-[10px] text-sky-400/90 bg-sky-950/60 px-1 py-0.2 rounded-sm border border-sky-800/40">Net</span>
                    </div>
                    <div class="text-base sm:text-2xl font-bold text-slate-100 font-mono tracking-tight">
                        {{ formatCurrency(stats.current_balance) }}
                    </div>
                    <div class="mt-1 text-[10px] sm:text-[11px] text-slate-500 truncate">
                        {{ isCentMode ? `≈ $${(stats.current_balance / 100).toFixed(2)} USD` : 'Modal + Total PnL' }}
                    </div>
                </div>

                <!-- Win Rate & Ratio -->
                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-sm relative overflow-hidden group">
                    <div class="flex items-center justify-between text-[11px] sm:text-xs text-slate-400 font-medium mb-1">
                        <span class="truncate">Win Rate</span>
                        <span class="text-[9px] sm:text-[10px] text-indigo-400/90 bg-indigo-950/60 px-1 py-0.2 rounded-sm border border-indigo-800/40">
                            {{ stats.winning_trades }}W / {{ stats.losing_trades }}L
                        </span>
                    </div>
                    <div class="text-base sm:text-2xl font-bold text-slate-100 font-mono tracking-tight">
                        {{ stats.win_rate }}%
                    </div>
                    <div class="mt-1 text-[10px] sm:text-[11px] text-slate-500 flex items-center justify-between truncate">
                        <span>{{ stats.total_trades }} Posisi</span>
                        <span class="text-slate-400">PF: {{ stats.profit_factor }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab Buttons (Fluid 3-column on mobile) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 border-b border-slate-800 pb-3">
                <div class="grid grid-cols-3 sm:flex items-center gap-1.5 sm:gap-2">
                    <button
                        @click="activeTab = 'trades'"
                        class="px-2 sm:px-3.5 py-2 sm:py-1.5 text-xs font-semibold rounded-sm transition flex items-center justify-center gap-1 text-center"
                        :class="activeTab === 'trades' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900 border border-transparent'"
                    >
                        <span>Transaksi</span>
                        <span class="px-1 py-0.1 bg-slate-800 text-[10px] rounded-sm text-slate-300">{{ props.trades.length }}</span>
                    </button>

                    <button
                        @click="activeTab = 'weekly'"
                        class="px-2 sm:px-3.5 py-2 sm:py-1.5 text-xs font-semibold rounded-sm transition flex items-center justify-center gap-1 text-center"
                        :class="activeTab === 'weekly' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900 border border-transparent'"
                    >
                        <span>Mingguan</span>
                        <span class="px-1 py-0.1 bg-slate-800 text-[10px] rounded-sm text-slate-300">{{ weekly_reports.length }}</span>
                    </button>

                    <button
                        @click="activeTab = 'monthly'"
                        class="px-2 sm:px-3.5 py-2 sm:py-1.5 text-xs font-semibold rounded-sm transition flex items-center justify-center gap-1 text-center"
                        :class="activeTab === 'monthly' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900 border border-transparent'"
                    >
                        <span>Bulanan</span>
                        <span class="px-1 py-0.1 bg-slate-800 text-[10px] rounded-sm text-slate-300">{{ monthly_reports.length }}</span>
                    </button>
                </div>

                <!-- Active Filter Indicator -->
                <div v-if="selectedWeekFilter || selectedMonthFilter" class="flex items-center justify-between sm:justify-end gap-2 bg-slate-900/60 p-1.5 rounded-sm sm:bg-transparent sm:p-0">
                    <span class="text-xs text-cyan-400 bg-cyan-950/60 border border-cyan-800/60 px-2 py-1 rounded-sm flex items-center gap-1.5">
                        Filter: {{ selectedWeekFilter ? 'Minggu ' + selectedWeekFilter : selectedMonthFilter }}
                    </span>
                    <button @click="clearPeriodFilter" class="text-xs text-slate-400 hover:text-white underline">Hapus Filter</button>
                </div>
            </div>

            <!-- TAB 1: SEMUA TRANSAKSI (CRUD) -->
            <div v-if="activeTab === 'trades'" class="space-y-3 sm:space-y-4">
                <!-- Filter Bar: Fully responsive -->
                <div class="bg-slate-900/70 border border-slate-800 p-2.5 sm:p-3 rounded-sm space-y-2.5 md:space-y-0 md:flex md:items-center md:justify-between md:gap-3">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 flex-1">
                        <!-- Search Box -->
                        <div class="relative w-full sm:w-56">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari pair, catatan..."
                                class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-xs px-3 py-2 sm:py-1.5 rounded-sm placeholder-slate-500"
                            />
                            <span v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2 sm:top-1.5 text-xs text-slate-500 hover:text-slate-300 cursor-pointer">✕</span>
                        </div>

                        <!-- Toggle Chips on mobile -->
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                            <!-- Type Toggle -->
                            <div class="inline-flex bg-slate-950 p-0.5 border border-slate-800 rounded-sm shrink-0">
                                <button
                                    @click="filterType = 'ALL'"
                                    class="px-2.5 py-1 text-xs rounded-sm transition"
                                    :class="filterType === 'ALL' ? 'bg-slate-800 text-slate-100 font-semibold' : 'text-slate-400 hover:text-slate-200'"
                                >
                                    Semua
                                </button>
                                <button
                                    @click="filterType = 'BUY'"
                                    class="px-2.5 py-1 text-xs rounded-sm transition"
                                    :class="filterType === 'BUY' ? 'bg-sky-950 text-sky-400 font-semibold border border-sky-800/50' : 'text-slate-400 hover:text-slate-200'"
                                >
                                    BUY
                                </button>
                                <button
                                    @click="filterType = 'SELL'"
                                    class="px-2.5 py-1 text-xs rounded-sm transition"
                                    :class="filterType === 'SELL' ? 'bg-rose-950 text-rose-400 font-semibold border border-rose-800/50' : 'text-slate-400 hover:text-slate-200'"
                                >
                                    SELL
                                </button>
                            </div>

                            <!-- Outcome Toggle -->
                            <div class="inline-flex bg-slate-950 p-0.5 border border-slate-800 rounded-sm shrink-0">
                                <button
                                    @click="filterOutcome = 'ALL'"
                                    class="px-2.5 py-1 text-xs rounded-sm transition"
                                    :class="filterOutcome === 'ALL' ? 'bg-slate-800 text-slate-100 font-semibold' : 'text-slate-400 hover:text-slate-200'"
                                >
                                    Semua
                                </button>
                                <button
                                    @click="filterOutcome = 'WIN'"
                                    class="px-2.5 py-1 text-xs rounded-sm transition"
                                    :class="filterOutcome === 'WIN' ? 'bg-teal-950 text-teal-400 font-semibold border border-teal-800/50' : 'text-slate-400 hover:text-slate-200'"
                                >
                                    Profit
                                </button>
                                <button
                                    @click="filterOutcome = 'LOSS'"
                                    class="px-2.5 py-1 text-xs rounded-sm transition"
                                    :class="filterOutcome === 'LOSS' ? 'bg-rose-950 text-rose-400 font-semibold border border-rose-800/50' : 'text-slate-400 hover:text-slate-200'"
                                >
                                    Loss
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-[11px] sm:text-xs text-slate-400 flex items-center justify-between md:justify-end gap-2">
                        <span>Menampilkan <strong>{{ filteredTrades.length }}</strong> trade</span>
                    </div>
                </div>

                <!-- 1. MOBILE VIEW: Native App Card List (sm:hidden) -->
                <div class="sm:hidden space-y-2.5">
                    <div
                        v-for="trade in filteredTrades"
                        :key="trade.id"
                        class="bg-slate-900/90 border border-slate-800 p-3.5 rounded-sm space-y-2.5 hover:border-slate-700 transition active:bg-slate-850"
                    >
                        <!-- Top line: Pair, Type, Lot, Time -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="px-1.5 py-0.5 bg-slate-800 text-slate-100 font-mono font-bold text-xs rounded-sm border border-slate-700/60">
                                    {{ trade.pair }}
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-sm text-[10px] font-bold uppercase tracking-wider"
                                    :class="trade.type === 'BUY' ? 'bg-sky-950/90 text-sky-400 border border-sky-800/60' : 'bg-rose-950/90 text-rose-400 border border-rose-800/60'"
                                >
                                    {{ trade.type }}
                                </span>
                                <span class="text-xs text-slate-300 font-mono font-medium">
                                    {{ trade.lot_size }} Lot
                                </span>
                            </div>
                            <span class="text-[11px] font-mono text-slate-400">
                                {{ trade.traded_at_formatted }}
                            </span>
                        </div>

                        <!-- Middle line: PnL Large & Notes -->
                        <div class="flex items-center justify-between bg-slate-950/70 p-2.5 rounded-sm border border-slate-800/80">
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Hasil PnL ({{ currencyUnit }})</div>
                                <div
                                    class="font-mono font-bold text-base"
                                    :class="trade.pnl > 0 ? 'text-teal-400' : (trade.pnl < 0 ? 'text-rose-400' : 'text-slate-300')"
                                >
                                    {{ trade.pnl > 0 ? '+' : '' }}{{ formatCurrency(trade.pnl) }}
                                </div>
                            </div>
                            <div class="text-right max-w-[55%]">
                                <div class="text-[10px] text-slate-500 uppercase font-semibold">Catatan</div>
                                <div class="text-xs text-slate-300 truncate" :title="trade.notes">
                                    {{ trade.notes || '-' }}
                                </div>
                            </div>
                        </div>

                        <!-- Bottom line: Actions with large touch targets -->
                        <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-800/60">
                            <button
                                @click="openEditModal(trade)"
                                class="px-3 py-1.5 text-xs font-semibold text-cyan-400 bg-cyan-950/40 hover:bg-cyan-950/80 rounded-sm border border-cyan-800/40 transition active:scale-95"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteTrade(trade.id)"
                                class="px-3 py-1.5 text-xs font-semibold text-rose-400 bg-rose-950/40 hover:bg-rose-950/80 rounded-sm border border-rose-800/40 transition active:scale-95"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>

                    <div v-if="filteredTrades.length === 0" class="py-12 text-center text-slate-500 bg-slate-900/40 border border-slate-800 rounded-sm p-4">
                        <p class="text-sm font-medium text-slate-400">Belum ada catatan transaksi.</p>
                        <p class="text-xs text-slate-500 mt-1">Gunakan tombol "+ Catat Trade" di atas untuk menambahkan.</p>
                    </div>
                </div>

                <!-- 2. DESKTOP VIEW: Data Table (hidden sm:block) -->
                <div class="hidden sm:block bg-slate-900/90 border border-slate-800 rounded-sm overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300 border-collapse">
                            <thead>
                                <tr class="bg-slate-950/80 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-3.5">Waktu</th>
                                    <th class="py-3 px-3">Pair</th>
                                    <th class="py-3 px-3">Tipe</th>
                                    <th class="py-3 px-3">Lot</th>
                                    <th class="py-3 px-3.5 text-right">Hasil PnL ({{ currencyUnit }})</th>
                                    <th class="py-3 px-4">Catatan / Setup</th>
                                    <th class="py-3 px-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/70">
                                <tr
                                    v-for="trade in filteredTrades"
                                    :key="trade.id"
                                    class="hover:bg-slate-800/40 transition group"
                                >
                                    <td class="py-3 px-3.5 font-mono text-slate-300 whitespace-nowrap">
                                        {{ trade.traded_at_formatted }}
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-slate-100">
                                        <span class="px-1.5 py-0.5 bg-slate-800 rounded-sm border border-slate-700/60 font-mono text-[11px]">
                                            {{ trade.pair }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span
                                            class="px-2 py-0.5 rounded-sm text-[10px] font-bold uppercase tracking-wider inline-block"
                                            :class="trade.type === 'BUY' ? 'bg-sky-950/80 text-sky-400 border border-sky-800/50' : 'bg-rose-950/80 text-rose-400 border border-rose-800/50'"
                                        >
                                            {{ trade.type }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-slate-200">
                                        {{ trade.lot_size }}
                                    </td>
                                    <td class="py-3 px-3.5 text-right font-mono font-bold text-sm whitespace-nowrap">
                                        <span
                                            class="inline-block px-2 py-0.5 rounded-sm"
                                            :class="trade.pnl > 0 ? 'text-teal-400 bg-teal-950/40 border border-teal-800/30' : (trade.pnl < 0 ? 'text-rose-400 bg-rose-950/40 border border-rose-800/30' : 'text-slate-400 bg-slate-800/40')"
                                        >
                                            {{ trade.pnl > 0 ? '+' : '' }}{{ formatCurrency(trade.pnl) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-slate-400 max-w-sm truncate" :title="trade.notes">
                                        {{ trade.notes || '-' }}
                                    </td>
                                    <td class="py-3 px-3.5 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button
                                                @click="openEditModal(trade)"
                                                class="px-2 py-1 text-[11px] font-medium text-cyan-400 hover:text-cyan-300 hover:bg-cyan-950/50 rounded-sm border border-cyan-900/40 transition"
                                                title="Edit Transaksi"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                @click="deleteTrade(trade.id)"
                                                class="px-2 py-1 text-[11px] font-medium text-rose-400 hover:text-rose-300 hover:bg-rose-950/50 rounded-sm border border-rose-900/40 transition"
                                                title="Hapus Transaksi"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredTrades.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-500">
                                        <div class="max-w-xs mx-auto space-y-2">
                                            <svg class="w-8 h-8 mx-auto text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            <p class="text-sm font-medium text-slate-400">Tidak ada catatan transaksi ditemukan.</p>
                                            <p class="text-xs text-slate-500">Gunakan tombol "Catat Transaksi" untuk memasukkan jurnal trade baru.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: LAPORAN MINGGUAN (WEEKLY REPORT) -->
            <div v-if="activeTab === 'weekly'" class="space-y-3 sm:space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div
                        v-for="week in weekly_reports"
                        :key="week.key"
                        class="bg-slate-900/90 border border-slate-800 p-3.5 sm:p-4 rounded-sm flex flex-col justify-between space-y-3 hover:border-slate-700 transition"
                    >
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-bold text-slate-200">{{ week.label }}</span>
                                <span
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded-sm border font-semibold"
                                    :class="week.total_pnl >= 0 ? 'bg-teal-950/60 text-teal-400 border-teal-800/40' : 'bg-rose-950/60 text-rose-400 border-rose-800/40'"
                                >
                                    {{ week.total_pnl >= 0 ? '+' : '' }}{{ formatCurrency(week.total_pnl) }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ week.start_date }} s/d {{ week.end_date }}
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-slate-950/70 p-2.5 rounded-sm border border-slate-800/80 text-center font-mono text-xs">
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase">Trades</div>
                                <div class="font-bold text-slate-200">{{ week.total_trades }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase">W / L</div>
                                <div class="font-bold text-slate-200">{{ week.win_trades }} / {{ week.loss_trades }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase">Win Rate</div>
                                <div class="font-bold" :class="week.win_rate >= 50 ? 'text-teal-400' : 'text-slate-300'">{{ week.win_rate }}%</div>
                            </div>
                        </div>

                        <button
                            @click="filterByWeek(week.key)"
                            class="w-full py-2 sm:py-1.5 text-xs font-semibold text-cyan-400 hover:text-cyan-300 bg-cyan-950/40 hover:bg-cyan-950/80 border border-cyan-800/40 rounded-sm transition flex items-center justify-center gap-1 active:scale-98"
                        >
                            Lihat Transaksi Minggu Ini →
                        </button>
                    </div>

                    <div v-if="weekly_reports.length === 0" class="col-span-full py-12 text-center text-slate-500 bg-slate-900/40 border border-slate-800 rounded-sm p-4">
                        Belum ada laporan mingguan. Catat transaksi trading terlebih dahulu.
                    </div>
                </div>
            </div>

            <!-- TAB 3: LAPORAN BULANAN (MONTHLY REPORT) -->
            <div v-if="activeTab === 'monthly'" class="space-y-3 sm:space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div
                        v-for="month in monthly_reports"
                        :key="month.key"
                        class="bg-slate-900/90 border border-slate-800 p-3.5 sm:p-4 rounded-sm flex flex-col justify-between space-y-3 hover:border-slate-700 transition"
                    >
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-bold text-slate-200 text-sm">{{ month.label }}</span>
                                <span
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded-sm border font-semibold"
                                    :class="month.total_pnl >= 0 ? 'bg-teal-950/60 text-teal-400 border-teal-800/40' : 'bg-rose-950/60 text-rose-400 border-rose-800/40'"
                                >
                                    {{ month.total_pnl >= 0 ? '+' : '' }}{{ formatCurrency(month.total_pnl) }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-400">
                                Total {{ month.total_trades }} transaksi pada bulan ini
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-slate-950/70 p-2.5 rounded-sm border border-slate-800/80 text-center font-mono text-xs">
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase">Trades</div>
                                <div class="font-bold text-slate-200">{{ month.total_trades }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase">W / L</div>
                                <div class="font-bold text-slate-200">{{ month.win_trades }} / {{ month.loss_trades }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-500 uppercase">Win Rate</div>
                                <div class="font-bold" :class="month.win_rate >= 50 ? 'text-teal-400' : 'text-slate-300'">{{ month.win_rate }}%</div>
                            </div>
                        </div>

                        <button
                            @click="filterByMonth(month.key)"
                            class="w-full py-2 sm:py-1.5 text-xs font-semibold text-cyan-400 hover:text-cyan-300 bg-cyan-950/40 hover:bg-cyan-950/80 border border-cyan-800/40 rounded-sm transition flex items-center justify-center gap-1 active:scale-98"
                        >
                            Lihat Transaksi Bulan Ini →
                        </button>
                    </div>

                    <div v-if="monthly_reports.length === 0" class="col-span-full py-12 text-center text-slate-500 bg-slate-900/40 border border-slate-800 rounded-sm p-4">
                        Belum ada laporan bulanan. Catat transaksi trading terlebih dahulu.
                    </div>
                </div>
            </div>
        </div>

        <!-- MOBILE FLOATING ACTION BUTTON (FAB) for fast logging on phones -->
        <button
            @click="openCreateModal"
            class="sm:hidden fixed bottom-6 right-6 z-40 w-14 h-14 bg-gradient-to-tr from-cyan-500 to-sky-400 text-slate-950 rounded-full flex items-center justify-center shadow-xl shadow-cyan-500/20 active:scale-90 transition transform"
            title="Catat Transaksi Baru"
        >
            <svg class="w-7 h-7 font-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </button>

        <!-- MODAL: CATAT / EDIT TRANSAKSI -->
        <div
            v-if="showTradeModal"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-950/80 backdrop-blur-sm p-0 sm:p-4 overflow-y-auto"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-t-lg sm:rounded-sm max-w-md w-full p-5 space-y-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-bold text-slate-100 text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-sm bg-cyan-400"></span>
                        {{ isEditing ? 'Edit Transaksi Trading' : 'Catat Transaksi Baru' }}
                    </h3>
                    <button @click="showTradeModal = false" class="text-slate-400 hover:text-slate-200 text-base p-1">✕</button>
                </div>

                <form @submit.prevent="submitTrade" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                        <!-- Waktu Eksekusi -->
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Tanggal & Waktu</label>
                            <input
                                v-model="tradeForm.traded_at"
                                type="datetime-local"
                                class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-sm sm:text-xs px-3 py-2.5 sm:py-2 rounded-sm"
                                required
                            />
                            <div v-if="tradeForm.errors.traded_at" class="text-rose-400 text-[11px] mt-1">{{ tradeForm.errors.traded_at }}</div>
                        </div>

                        <!-- Pair -->
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Pair / Instrumen</label>
                            <input
                                v-model="tradeForm.pair"
                                type="text"
                                placeholder="XAUUSD"
                                class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-sm sm:text-xs px-3 py-2.5 sm:py-2 rounded-sm uppercase font-mono"
                                required
                            />
                            <div v-if="tradeForm.errors.pair" class="text-rose-400 text-[11px] mt-1">{{ tradeForm.errors.pair }}</div>
                        </div>

                        <!-- Tipe (BUY / SELL) -->
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Tipe Posisi</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="tradeForm.type = 'BUY'"
                                    class="py-2.5 sm:py-1.5 text-xs font-bold rounded-sm border transition"
                                    :class="tradeForm.type === 'BUY' ? 'bg-sky-950 text-sky-400 border-sky-700' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
                                >
                                    BUY (Long)
                                </button>
                                <button
                                    type="button"
                                    @click="tradeForm.type = 'SELL'"
                                    class="py-2.5 sm:py-1.5 text-xs font-bold rounded-sm border transition"
                                    :class="tradeForm.type === 'SELL' ? 'bg-rose-950 text-rose-400 border-rose-700' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-slate-200'"
                                >
                                    SELL (Short)
                                </button>
                            </div>
                        </div>

                        <!-- Lot Size -->
                        <div>
                            <label class="block text-slate-300 font-medium mb-1">Lot Size {{ isCentMode ? '(Lot Cent)' : '(Standard Lot)' }}</label>
                            <input
                                v-model="tradeForm.lot_size"
                                type="number"
                                step="0.001"
                                placeholder="0.01"
                                class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-sm sm:text-xs px-3 py-2.5 sm:py-2 rounded-sm font-mono"
                                required
                            />
                            <div v-if="tradeForm.errors.lot_size" class="text-rose-400 text-[11px] mt-1">{{ tradeForm.errors.lot_size }}</div>
                        </div>

                        <!-- PnL Hasil Bersih -->
                        <div class="sm:col-span-2">
                            <label class="block text-slate-300 font-medium mb-1">
                                Hasil Untung / Rugi ({{ currencyUnit }})
                            </label>
                            <input
                                v-model="tradeForm.pnl"
                                type="number"
                                step="0.01"
                                :placeholder="isCentMode ? 'Contoh: 150.00 (profit) atau -50.00 (loss)' : 'Contoh: 15.00 ($15 profit) atau -5.00 (loss)'"
                                class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-sm sm:text-xs px-3 py-2.5 sm:py-2 rounded-sm font-mono"
                                required
                            />
                            <p class="text-[10px] text-slate-500 mt-1">
                                Masukkan angka positif jika profit (contoh: 25.00), atau minus jika loss (contoh: -15.50) dalam satuan <strong>{{ currencyUnit }}</strong>.
                            </p>
                            <div v-if="tradeForm.errors.pnl" class="text-rose-400 text-[11px] mt-1">{{ tradeForm.errors.pnl }}</div>
                        </div>

                        <!-- Catatan / Evaluasi -->
                        <div class="sm:col-span-2">
                            <label class="block text-slate-300 font-medium mb-1">Catatan / Setup Trading (Opsional)</label>
                            <textarea
                                v-model="tradeForm.notes"
                                rows="2"
                                placeholder="Alasan entry, setup rejection, target TP/SL, evaluasi emosi/psikologi..."
                                class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-sm sm:text-xs px-3 py-2 rounded-sm placeholder-slate-600"
                            ></textarea>
                            <div v-if="tradeForm.errors.notes" class="text-rose-400 text-[11px] mt-1">{{ tradeForm.errors.notes }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-800 pt-3">
                        <button
                            type="button"
                            @click="showTradeModal = false"
                            class="px-4 py-2.5 sm:py-1.5 text-xs text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-sm transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="tradeForm.processing"
                            class="px-5 py-2.5 sm:py-1.5 text-xs font-semibold text-slate-950 bg-gradient-to-r from-cyan-400 to-sky-400 hover:from-cyan-300 hover:to-sky-300 rounded-sm transition shadow-sm active:scale-98"
                        >
                            {{ tradeForm.processing ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Simpan Transaksi') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: PENGATURAN MODAL & MODE AKUN -->
        <div
            v-if="showSettingsModal"
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-950/80 backdrop-blur-sm p-0 sm:p-4"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-t-lg sm:rounded-sm max-w-md w-full p-5 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="font-bold text-slate-100 text-sm flex items-center gap-2">
                        <span class="w-2 h-2 rounded-sm bg-cyan-400"></span>
                        Pengaturan Akun & Modal Awal
                    </h3>
                    <button @click="showSettingsModal = false" class="text-slate-400 hover:text-slate-200 text-base p-1">✕</button>
                </div>

                <form @submit.prevent="submitSettings" class="space-y-4 text-xs">
                    <!-- Pilihan Mode Akun -->
                    <div>
                        <label class="block text-slate-300 font-medium mb-1.5">Tipe Akun Trading</label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                type="button"
                                @click="settingsForm.account_type = 'CENT'"
                                class="p-3 rounded-sm border text-left transition flex flex-col justify-between"
                                :class="settingsForm.account_type === 'CENT' ? 'bg-amber-950/50 border-amber-500 text-amber-200' : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <div class="font-bold text-xs flex items-center gap-1.5">
                                    <span>🪙 Akun Cent</span>
                                    <span v-if="settingsForm.account_type === 'CENT'" class="text-[10px] bg-amber-500/20 text-amber-300 px-1 py-0.2 rounded-sm font-normal">Aktif</span>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Satuan Cent (USC / ¢). $10 USD = 1,000 USC.</p>
                            </button>

                            <button
                                type="button"
                                @click="settingsForm.account_type = 'USD'"
                                class="p-3 rounded-sm border text-left transition flex flex-col justify-between"
                                :class="settingsForm.account_type === 'USD' ? 'bg-sky-950/50 border-sky-500 text-sky-200' : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <div class="font-bold text-xs flex items-center gap-1.5">
                                    <span>💵 Akun Standar</span>
                                    <span v-if="settingsForm.account_type === 'USD'" class="text-[10px] bg-sky-500/20 text-sky-300 px-1 py-0.2 rounded-sm font-normal">Aktif</span>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Satuan Dolar (USD / $). Standar forex/emas.</p>
                            </button>
                        </div>
                    </div>

                    <!-- Modal Awal -->
                    <div>
                        <label class="block text-slate-300 font-medium mb-1">
                            Modal Awal ({{ settingsForm.account_type === 'CENT' ? 'USC / Cent' : 'USD ($)' }})
                        </label>
                        <input
                            v-model="settingsForm.initial_balance"
                            type="number"
                            step="0.01"
                            min="0"
                            :placeholder="settingsForm.account_type === 'CENT' ? 'Contoh: 10000.00' : 'Contoh: 1000.00'"
                            class="w-full bg-slate-950 border border-slate-800 focus:border-cyan-500 focus:ring-0 text-slate-200 text-sm sm:text-xs px-3 py-2.5 sm:py-2 rounded-sm font-mono"
                            required
                        />
                        <p class="text-[11px] text-slate-500 mt-1.5">
                            {{ settingsForm.account_type === 'CENT' ? 'Jika modal Anda $50 USD, masukkan 5000 USC.' : 'Modal dasar akun untuk kalkulasi pertumbuhan saldo dan net balance.' }}
                        </p>
                        <div v-if="settingsForm.errors.initial_balance" class="text-rose-400 text-[11px] mt-1">{{ settingsForm.errors.initial_balance }}</div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-800 pt-3">
                        <button
                            type="button"
                            @click="showSettingsModal = false"
                            class="px-4 py-2.5 sm:py-1.5 text-xs text-slate-400 hover:text-slate-200 rounded-sm transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="settingsForm.processing"
                            class="px-5 py-2.5 sm:py-1.5 text-xs font-semibold text-slate-950 bg-gradient-to-r from-cyan-400 to-sky-400 hover:from-cyan-300 hover:to-sky-300 rounded-sm transition shadow-sm active:scale-98"
                        >
                            {{ settingsForm.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>