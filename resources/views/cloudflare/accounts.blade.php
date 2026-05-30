<x-app-layout>
    <div class="max-w-4xl mx-auto space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Cloudflare</h1>
            <p class="text-gray-500 text-sm mt-1">Hubungkan API Token Cloudflare untuk kelola DNS</p>
        </div>

        @if($accounts->isNotEmpty())
            {{-- Connected Account --}}
            @foreach($accounts as $account)
            <div class="rounded-2xl bg-gradient-to-br from-emerald-500/5 to-emerald-600/5 border border-emerald-500/20 overflow-hidden">
                <div class="px-5 py-4 border-b border-emerald-500/10 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-white">Terhubung</h2>
                            <p class="text-xs text-emerald-400/70">Koneksi aktif</p>
                        </div>
                    </div>
                    <form action="{{ route('cloudflare.accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Yakin putuskan koneksi?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-all">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Putuskan
                        </button>
                    </form>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="space-y-1">
                            <p class="text-[11px] text-gray-500 uppercase tracking-wider font-medium">Akun</p>
                            <p class="text-sm text-white font-medium">{{ $account->name }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] text-gray-500 uppercase tracking-wider font-medium">Email</p>
                            <p class="text-sm text-gray-300">{{ $account->account_email ?? '-' }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[11px] text-gray-500 uppercase tracking-wider font-medium">API Token</p>
                            <p class="text-sm text-gray-300 font-mono tracking-wider">{{ substr($account->api_token, 0, 8) }}••••••••{{ substr($account->api_token, -4) }}</p>
                        </div>
                    </div>
                    <div class="mt-5 pt-4 border-t border-emerald-500/10 flex items-center gap-3">
                        <a href="{{ route('cloudflare.accounts.zones', $account) }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            Browse Zones
                        </a>
                        <a href="{{ route('tracked-domains.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-300 text-sm font-medium hover:bg-gray-700/50 hover:text-white active:scale-[0.98] transition-all duration-200">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Lihat Tracked Domains
                        </a>
                    </div>
                </div>
            </div>
            @endforeach

            {{-- Add another --}}
            <details class="group">
                <summary class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-300 cursor-pointer transition-colors list-none">
                    <svg class="w-4 h-4 group-open:rotate-45 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah akun lain
                </summary>
                <div class="mt-4">
                    @include('cloudflare._add-form')
                </div>
            </details>
        @else
            {{-- No account --}}
            @include('cloudflare._add-form')
        @endif
    </div>
</x-app-layout>
