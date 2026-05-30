<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'AutoDNS') }} @isset($title) — {{ $title }} @endisset</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-950 text-gray-100">
        <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
            {{-- Sidebar --}}
            <aside class="fixed inset-y-0 left-0 z-40 w-[260px] bg-gray-900/80 backdrop-blur-xl border-r border-gray-800/50 lg:static hidden lg:flex lg:flex-col">
                {{-- Logo --}}
                <div class="flex items-center gap-3 px-5 h-16 shrink-0 border-b border-gray-800/50">
                    <div class="relative w-8 h-8">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-lg blur-sm opacity-60"></div>
                        <div class="relative w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        </div>
                    </div>
                    <div>
                        <span class="text-base font-bold text-white tracking-tight">AutoDNS</span>
                        <span class="block text-[10px] text-gray-500 tracking-wider uppercase">Management</span>
                    </div>
                </div>

                {{-- Nav --}}
                <nav class="flex-1 px-3 py-5 space-y-1 overflow-y-auto">
                    @php
                        $navItems = [
                            ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                            ['route' => 'cloudflare.accounts.index', 'label' => 'Cloudflare', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                            ['route' => 'tracked-domains.index', 'label' => 'Tracked Domains', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                            ['route' => 'dns.update', 'label' => 'DNS Update', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
                            ['route' => 'logs', 'label' => 'Activity Logs', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                        ];
                    @endphp
                    @foreach($navItems as $item)
                        @php $active = request()->routeIs($item['route']); @endphp
                        <a href="{{ route($item['route']) }}"
                           @class([
                               'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 group',
                               'text-white bg-gradient-to-r from-blue-500/15 to-cyan-500/10 border border-blue-500/10 shadow-sm shadow-blue-500/5' => $active,
                               'text-gray-400 hover:text-gray-200 hover:bg-gray-800/40' => !$active,
                           ])>
                            <span @class([
                                'w-5 h-5 shrink-0 transition-colors duration-200',
                                'text-blue-400' => $active,
                                'text-gray-500 group-hover:text-gray-300' => !$active,
                            ])>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $item['icon'] }}"/>
                                </svg>
                            </span>
                            <span>{{ $item['label'] }}</span>
                            @if($active)
                                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                {{-- User --}}
                <div class="p-3 border-t border-gray-800/50">
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-gray-800/30">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-xs font-bold text-white shadow-sm">
                            {{ substr(Auth::user()->name, 0, 2) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-200 truncate">{{ Auth::user()->name }}</p>
                            <p class="text-[11px] text-gray-500 truncate">{{ Auth::user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="p-1.5 rounded-lg text-gray-500 hover:text-gray-200 hover:bg-gray-700/50 transition-all" title="Logout">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Mobile header --}}
            <header class="lg:hidden flex items-center justify-between h-14 px-4 bg-gray-900/80 backdrop-blur-xl border-b border-gray-800/50 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    </div>
                    <span class="text-base font-bold text-white">AutoDNS</span>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </header>

            {{-- Mobile nav --}}
            <nav class="lg:hidden flex items-center gap-1 px-2 py-2 bg-gray-900/80 backdrop-blur-xl border-b border-gray-800/50 overflow-x-auto sticky top-14 z-20">
                @foreach($navItems as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap',
                           'text-white bg-blue-500/20 border border-blue-500/20' => $active,
                           'text-gray-400 hover:text-gray-200' => !$active,
                       ])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Main --}}
            <main class="min-h-screen lg:min-h-0">
                {{-- Flash messages --}}
                @if (session('success'))
                    <div class="mx-4 mt-4 lg:mx-8 lg:mt-6 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="mx-4 mt-4 lg:mx-8 lg:mt-6 p-3.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ session('error') }}
                    </div>
                @endif
                @if (session('info'))
                    <div class="mx-4 mt-4 lg:mx-8 lg:mt-6 p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ session('info') }}
                    </div>
                @endif

                <div class="p-4 lg:p-8">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
