<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-cyan-500 selection:text-white">
        <!-- Top Navbar -->
        <nav class="border-b border-slate-800/80 bg-slate-900/95 backdrop-blur-md sticky top-0 z-40">
            <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
                <div class="flex h-14 sm:h-16 justify-between items-center">
                    <div class="flex items-center gap-4 sm:gap-8">
                        <!-- Logo -->
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('dashboard')" class="flex items-center gap-2 group">
                                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-sm bg-gradient-to-tr from-cyan-600 to-sky-400 flex items-center justify-center font-black text-slate-950 text-sm sm:text-base shadow-sm group-hover:scale-105 transition-transform">
                                    X
                                </div>
                                <span class="font-bold text-base sm:text-lg tracking-tight bg-gradient-to-r from-slate-100 via-slate-200 to-cyan-300 bg-clip-text text-transparent">
                                    XAU Journal
                                </span>
                            </Link>
                        </div>

                        <!-- Navigation Links (Desktop) -->
                        <div class="hidden sm:flex space-x-4 items-center">
                            <NavLink
                                :href="route('trades.index')"
                                :active="route().current('trades.index') || route().current('dashboard')"
                                class="px-3 py-1.5 rounded-sm text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition"
                            >
                                Journal & Laporan
                            </NavLink>
                        </div>
                    </div>

                    <!-- Desktop User Dropdown -->
                    <div class="hidden sm:flex sm:items-center sm:gap-3">
                        <div class="relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-sm border border-slate-700/80 bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700/80 hover:text-white transition focus:outline-none"
                                    >
                                        <div class="w-4 h-4 rounded-sm bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-[10px] font-bold uppercase">
                                            {{ $page.props.auth.user.name.charAt(0) }}
                                        </div>
                                        <span class="max-w-[120px] truncate">{{ $page.props.auth.user.name }}</span>

                                        <svg
                                            class="h-3.5 w-3.5 text-slate-400"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="bg-slate-900 border border-slate-800 rounded-sm py-1 shadow-xl">
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                            class="text-slate-300 hover:bg-slate-800 hover:text-white text-xs"
                                        >
                                            Profil Akun
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                            class="text-rose-400 hover:bg-slate-800 hover:text-rose-300 text-xs"
                                        >
                                            Keluar (Logout)
                                        </DropdownLink>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="flex items-center gap-2 sm:hidden">
                        <button
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center rounded-sm p-1.5 text-slate-400 hover:bg-slate-800 hover:text-slate-200 focus:outline-none active:bg-slate-800"
                        >
                            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex': !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex': showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Responsive Navigation Menu Dropdown for Mobile -->
            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="sm:hidden border-t border-slate-800 bg-slate-900 px-3 pt-2 pb-3 space-y-2 shadow-2xl"
            >
                <div class="px-2 py-1.5 bg-slate-950 rounded-sm border border-slate-800/80 mb-2">
                    <div class="text-xs font-bold text-slate-200">
                        {{ $page.props.auth.user.name }}
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono">
                        {{ $page.props.auth.user.email }}
                    </div>
                </div>

                <ResponsiveNavLink
                    :href="route('trades.index')"
                    :active="route().current('trades.index')"
                    class="rounded-sm text-xs font-semibold text-slate-300 hover:bg-slate-800"
                >
                    Journal & Laporan
                </ResponsiveNavLink>

                <ResponsiveNavLink :href="route('profile.edit')" class="rounded-sm text-xs text-slate-300 hover:bg-slate-800">
                    Pengaturan Profil
                </ResponsiveNavLink>
                <ResponsiveNavLink
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-sm text-xs text-rose-400 hover:text-rose-300 hover:bg-rose-950/30"
                >
                    Keluar (Log Out)
                </ResponsiveNavLink>
            </div>
        </nav>

        <!-- Page Heading -->
        <header class="border-b border-slate-800/60 bg-slate-900/40" v-if="$slots.header">
            <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 py-3 sm:py-4">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main class="py-3 sm:py-6">
            <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>
    </div>
</template>
