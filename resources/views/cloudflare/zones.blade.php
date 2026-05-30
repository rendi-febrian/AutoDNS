<x-app-layout>
    <div class="max-w-5xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex items-start gap-4">
            <a href="{{ route('cloudflare.accounts.index') }}" class="p-2 rounded-xl bg-gray-800/50 text-gray-500 hover:text-gray-200 hover:bg-gray-700/50 transition-all">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">{{ $cloudflareAccount->name }}</h1>
                <p class="text-gray-500 text-sm mt-1">{{ $cloudflareAccount->account_email }} — Pilih zone & record untuk di-track ke auto-sync</p>
            </div>
        </div>

        {{-- Zones --}}
        @if(empty($zones))
        <div class="rounded-2xl bg-gray-900/20 border border-gray-800/30 p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-gray-800/50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
            </div>
            <p class="text-gray-500 text-sm">Gagal memuat zones dari Cloudflare.</p>
        </div>
        @else
        <div class="grid gap-3">
            @foreach($zones as $zone)
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden hover:border-gray-700/50 transition-all duration-300">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-400/20 flex items-center justify-center border border-blue-500/10 text-[10px] font-bold text-blue-400 shrink-0">
                            {{ substr($zone['name'], 0, 2) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-200">{{ $zone['name'] }}</h3>
                            <p class="text-xs text-gray-500">
                                ID: <span class="font-mono text-gray-600">{{ substr($zone['id'], 0, 12) }}...</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full font-medium {{ ($zone['status'] ?? '') === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' }}">
                            <span class="w-1 h-1 rounded-full {{ ($zone['status'] ?? '') === 'active' ? 'bg-emerald-400' : 'bg-gray-400' }}"></span>
                            {{ $zone['status'] ?? 'unknown' }}
                        </span>
                        <a href="{{ route('cloudflare.accounts.sync-zone-records', [$cloudflareAccount, $zone['id']]) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-500/10 text-blue-400 text-xs font-medium hover:bg-blue-500/20 active:scale-[0.98] transition-all duration-200">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            Lihat Records
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</x-app-layout>
