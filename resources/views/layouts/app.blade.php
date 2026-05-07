<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Peblar') — Laadpaal Beheer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style type="text/tailwindcss">
        [x-cloak] { display: none !important; }
        .nav-link { @apply flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors; }
        .nav-link:hover { @apply bg-white/10; }
        .nav-link.active { @apply bg-white/20; }
        .card { @apply bg-white rounded-2xl shadow-md border border-gray-200 p-5; }
        .badge-urgent  { @apply bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-semibold; }
        .badge-high    { @apply bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full text-xs font-semibold; }
        .badge-normal  { @apply bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs font-semibold; }
        .badge-low     { @apply bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs font-semibold; }
        .badge-stop    { @apply bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs font-semibold; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Navigation -->
<nav class="bg-gradient-to-r from-green-700 to-green-600 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center gap-2 shrink-0">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span class="text-lg font-bold tracking-tight">
                    <span class="sm:hidden">Peblar</span>
                    <span class="hidden sm:inline">Peblar Load Balancer</span>
                </span>
            </div>
            <div class="flex items-center gap-1 overflow-x-auto">
                <a href="{{ route('dashboard') }}"
                   class="nav-link shrink-0 {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   title="Dashboard">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="hidden sm:inline">Dashboard</span>
                </a>
                <a href="{{ route('schedule.index') }}"
                   class="nav-link shrink-0 {{ request()->routeIs('schedule.*') ? 'active' : '' }}"
                   title="Schema">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="hidden sm:inline">Schema</span>
                </a>
                <a href="{{ route('strategy') }}"
                   class="nav-link shrink-0 {{ request()->routeIs('strategy') ? 'active' : '' }}"
                   title="Strategie">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span class="hidden sm:inline">Strategie</span>
                </a>
                <a href="{{ route('history') }}"
                   class="nav-link shrink-0 {{ request()->routeIs('history') ? 'active' : '' }}"
                   title="Geschiedenis">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span class="hidden sm:inline">Geschiedenis</span>
                </a>
                <a href="{{ route('settings.index') }}"
                   class="nav-link shrink-0 {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                   title="Instellingen">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="hidden sm:inline">Instellingen</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Flash messages -->
@if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         x-transition class="bg-green-50 border-b border-green-200 px-4 py-3 text-green-800 text-sm text-center">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="bg-red-50 border-b border-red-200 px-4 py-3 text-red-800 text-sm text-center">
        {{ session('error') }}
    </div>
@endif

<!-- Main content -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    @yield('content')
</main>

@stack('scripts')
</body>
</html>
