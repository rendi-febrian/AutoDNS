<x-app-layout>
    <div class="max-w-6xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Tracked Domains</h1>
                <p class="text-gray-500 text-sm mt-1">Domain yang auto-sync A record ke IP server</p>
            </div>
            <form action="{{ route('tracked-domains.sync-all') }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Sync All to Server IP
                </button>
            </form>
        </div>

        {{-- Add + Import --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- Add --}}
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden group hover:border-gray-700/50 transition-all duration-300">
                <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-2.5">
                    <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                    <h2 class="text-sm font-semibold text-white">Add Domain</h2>
                </div>
                <form action="{{ route('tracked-domains.store') }}" method="POST" class="p-5 space-y-4">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Domain</label>
                            <input type="text" name="domain_name" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200" placeholder="example.com">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Zone</label>
                            <select name="zone_name" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200">
                                <option value="">Pilih zone...</option>
                                @foreach($zones as $zone)
                                <option value="{{ $zone->name }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Link DNS Record <span class="text-gray-600">(opsional)</span></label>
                            <select name="dns_record_id" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200">
                                <option value="">No link...</option>
                                @foreach($zones as $zone)
                                <optgroup label="{{ $zone->name }}">
                                    @foreach($zone->dnsRecords->where('type', 'A') as $record)
                                    <option value="{{ $record->id }}">{{ $record->name }} ({{ $record->content }})</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add to Tracking
                    </button>
                </form>
            </div>

            {{-- Import --}}
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden group hover:border-gray-700/50 transition-all duration-300">
                <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-2.5">
                    <div class="w-2 h-2 rounded-full bg-violet-400"></div>
                    <h2 class="text-sm font-semibold text-white">Import from Config</h2>
                </div>
                <form action="{{ route('tracked-domains.import-config') }}" method="POST" class="p-5 space-y-4">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Source</label>
                            <select name="config_type" id="configType" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/20 outline-none transition-all duration-200">
                                <option value="nginx">Nginx (sites-enabled)</option>
                                <option value="apache">Apache (sites-enabled)</option>
                                <option value="manual">Manual Paste</option>
                            </select>
                        </div>
                        <div id="manualInput">
                            <label class="block text-xs font-medium text-gray-400 mb-1.5">Domain List <span class="text-gray-600">(1 per line)</span></label>
                            <textarea name="config_content" rows="5" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/20 outline-none transition-all duration-200" placeholder="example.com&#10;www.example.com&#10;app.example.com"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-violet-500 to-purple-500 text-white text-sm font-semibold hover:from-violet-400 hover:to-purple-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-violet-500/10">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Import Domains
                    </button>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800/50">
                            <th class="text-left px-5 py-3.5 font-semibold">Domain</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Zone</th>
                            <th class="text-left px-5 py-3.5 font-semibold">IP</th>
                            <th class="text-center px-5 py-3.5 font-semibold">Status</th>
                            <th class="text-right px-5 py-3.5 font-semibold">Last Sync</th>
                            <th class="text-right px-5 py-3.5 font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/30">
                        @forelse($trackedDomains as $domain)
                        <tr class="hover:bg-gray-800/20 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="text-gray-300 font-mono text-xs font-medium">{{ $domain->domain_name }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $domain->zone_name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="text-gray-400 font-mono text-xs">{{ $domain->ip_address ?? '-' }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full font-medium {{ $domain->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-gray-500/10 text-gray-400' }}">
                                    <span class="w-1 h-1 rounded-full {{ $domain->is_active ? 'bg-emerald-400' : 'bg-gray-400' }}"></span>
                                    {{ $domain->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-gray-600 text-xs">{{ $domain->last_synced_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <form action="{{ route('tracked-domains.destroy', $domain) }}" method="POST" class="inline" onsubmit="return confirm('Hapus dari tracking?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-gray-500 text-sm">Belum ada tracked domain. Tambah atau import dari config.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('configType')?.addEventListener('change', function() {
            const manualInput = document.getElementById('manualInput');
            manualInput.style.display = this.value === 'manual' ? 'block' : 'none';
        });
        document.getElementById('configType')?.dispatchEvent(new Event('change'));
    </script>
    @endpush
</x-app-layout>
