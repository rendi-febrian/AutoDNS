<x-app-layout>
    <div class="max-w-7xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Dashboard</h1>
                <p class="text-gray-500 text-sm mt-1">Overview of your DNS infrastructure</p>
            </div>
            <span class="text-xs text-gray-600 bg-gray-900/50 px-3 py-1.5 rounded-lg border border-gray-800/50">
                IP: <span class="text-gray-300 font-mono">{{ $serverIp }}</span>
            </span>
        </div>

        {{-- Onboarding --}}
        @if($totalAccounts === 0)
        <div class="rounded-2xl bg-gradient-to-r from-blue-500/5 to-cyan-500/5 border border-blue-500/20 p-6 lg:p-8">
            <div class="flex items-start gap-5">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shrink-0 shadow-lg shadow-blue-500/20">
                    <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-white">Welcome to AutoDNS</h2>
                    <p class="text-sm text-gray-400 mt-1 leading-relaxed">
                        Get started by connecting your Cloudflare account. Once connected, you can browse zones, sync DNS records, and enable auto-sync for your domains.
                    </p>
                    <div class="flex flex-wrap gap-3 mt-4">
                        <a href="{{ route('cloudflare.accounts.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm font-semibold shadow-lg shadow-blue-500/20 hover:shadow-blue-500/30 hover:from-blue-400 hover:to-blue-500 transition-all duration-200">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Connect Cloudflare
                        </a>
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-300 text-sm font-medium hover:bg-gray-800 hover:border-gray-600/50 transition-all">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Invite Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            @php
                $stats = [
                    ['label' => 'Server IP', 'value' => $serverIp, 'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'from-blue-500/20 to-blue-600/10 text-blue-400', 'mono' => true],
                    ['label' => 'Zones', 'value' => $totalZones, 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9', 'color' => 'from-emerald-500/20 to-emerald-600/10 text-emerald-400'],
                    ['label' => 'Tracked Domains', 'value' => "$activeTracked/$totalTracked", 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'from-violet-500/20 to-violet-600/10 text-violet-400'],
                    ['label' => 'Cloudflare Accounts', 'value' => $totalAccounts, 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'color' => 'from-amber-500/20 to-amber-600/10 text-amber-400'],
                ];
            @endphp
            @foreach($stats as $s)
            <div class="group relative p-4 lg:p-5 rounded-2xl bg-gray-900/50 border border-gray-800/50 hover:border-gray-700/50 transition-all duration-300">
                <div class="flex items-start justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-gradient-to-br {{ $s['color'] }} bg-gray-800/50">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['icon'] }}"/></svg>
                    </div>
                </div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wider">{{ $s['label'] }}</p>
                <p class="text-xl lg:text-2xl font-bold text-white mt-1 {{ isset($s['mono']) ? 'font-mono tracking-tight' : '' }}">{{ $s['value'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Quick Actions --}}
        @if($totalAccounts > 0)
        <div>
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @php
                    $actions = [
                        ['route' => 'cloudflare.accounts.index', 'label' => 'Browse Zones', 'icon' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 'color' => 'from-blue-500/15 to-blue-600/5 text-blue-400 border-blue-500/10 hover:bg-blue-500/5'],
                        ['route' => 'tracked-domains.index', 'label' => 'Tracked Domains', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'from-violet-500/15 to-violet-600/5 text-violet-400 border-violet-500/10 hover:bg-violet-500/5'],
                        ['route' => 'dns.update', 'label' => 'DNS Update', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'color' => 'from-amber-500/15 to-amber-600/5 text-amber-400 border-amber-500/10 hover:bg-amber-500/5'],
                        ['route' => 'logs', 'label' => 'Activity Logs', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'color' => 'from-emerald-500/15 to-emerald-600/5 text-emerald-400 border-emerald-500/10 hover:bg-emerald-500/5'],
                    ];
                @endphp
                @foreach($actions as $a)
                <a href="{{ route($a['route']) }}"
                   class="flex items-center gap-3.5 px-4 py-4 rounded-xl bg-gray-900/30 border {{ $a['color'] }} transition-all duration-200 group">
                    <div class="p-2 rounded-lg bg-gray-800/50">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $a['icon'] }}"/></svg>
                    </div>
                    <span class="text-sm font-medium text-gray-300 group-hover:text-white transition-colors">{{ $a['label'] }}</span>
                    <svg class="w-4 h-4 ml-auto text-gray-600 group-hover:text-gray-400 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Zones + Logs --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Zones --}}
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-800/50 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                        <h2 class="text-sm font-semibold text-white">Zones</h2>
                    </div>
                    @if($zones->isNotEmpty())
                    <span class="text-xs text-gray-500">{{ $zones->count() }} total</span>
                    @endif
                </div>
                @if($zones->isNotEmpty())
                <div class="divide-y divide-gray-800/30">
                    @foreach($zones as $zone)
                    <a href="{{ route('zones.records', $zone) }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-800/20 transition-colors group">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-500/20 to-cyan-400/20 flex items-center justify-center text-[10px] font-bold text-blue-400 border border-blue-500/10 shrink-0">
                                {{ substr($zone->name, 0, 2) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-200 group-hover:text-white transition-colors truncate">{{ $zone->name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $zone->cloudflareAccount->name }} · {{ $zone->dnsRecords->count() }} records</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full font-medium {{ $zone->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                <span class="w-1 h-1 rounded-full {{ $zone->status === 'active' ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                {{ $zone->status }}
                            </span>
                            <svg class="w-4 h-4 text-gray-600 group-hover:text-gray-400 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </a>
                    @endforeach
                </div>
                @else
                <div class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">No zones yet. <a href="{{ route('cloudflare.accounts.index') }}" class="text-blue-400 hover:text-blue-300">Connect Cloudflare</a></p>
                </div>
                @endif
            </div>

            {{-- Recent Activity --}}
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-800/50 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                        <h2 class="text-sm font-semibold text-white">Recent Activity</h2>
                    </div>
                    @if($recentLogs->isNotEmpty())
                    <a href="{{ route('logs') }}" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">View all</a>
                    @endif
                </div>
                @if($recentLogs->isNotEmpty())
                <div class="divide-y divide-gray-800/30">
                    @foreach($recentLogs as $log)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-1.5 h-1.5 rounded-full shrink-0 {{ $log->status === 'success' ? 'bg-emerald-400' : 'bg-red-400' }}"></div>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-300 font-mono text-xs truncate">{{ $log->record_name }}</p>
                                <p class="text-[11px] text-gray-500">
                                    <span class="text-gray-600">{{ $log->old_ip ?? '-' }}</span>
                                    <span class="text-gray-600 mx-1">→</span>
                                    <span class="text-gray-400">{{ $log->new_ip }}</span>
                                </p>
                            </div>
                        </div>
                        <span class="text-[11px] text-gray-600 shrink-0">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-5 py-8 text-center">
                    <p class="text-sm text-gray-500">No activity yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
