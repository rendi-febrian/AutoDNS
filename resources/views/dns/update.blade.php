<x-app-layout>
    <div class="max-w-6xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">DNS Update</h1>
                <p class="text-gray-500 text-sm mt-1">Manual update single atau bulk DNS record</p>
            </div>
        </div>

        {{-- Update Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- Single --}}
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden group hover:border-gray-700/50 transition-all duration-300">
                <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-2.5">
                    <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                    <h2 class="text-sm font-semibold text-white">Single Update</h2>
                </div>
                <form action="{{ route('dns.update.single') }}" method="POST" class="p-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">DNS Record</label>
                        <select name="dns_record_id" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200">
                            <option value="">Pilih record...</option>
                            @foreach($zones as $zone)
                            <optgroup label="{{ $zone->name }}">
                                @foreach($zone->dnsRecords->where('type', 'A') as $record)
                                <option value="{{ $record->id }}">{{ $record->name }} ({{ $record->content }})</option>
                                @endforeach
                            </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">IP Baru</label>
                        <input type="text" name="ip_address" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200" placeholder="0.0.0.0">
                    </div>
                    <button type="submit" class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Update Single
                    </button>
                </form>
            </div>

            {{-- Bulk --}}
            <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden group hover:border-gray-700/50 transition-all duration-300">
                <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-2.5">
                    <div class="w-2 h-2 rounded-full bg-violet-400"></div>
                    <h2 class="text-sm font-semibold text-white">Bulk Update</h2>
                </div>
                <form action="{{ route('dns.update.bulk') }}" method="POST" class="p-5 space-y-4" id="bulkForm">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Zone</label>
                        <select name="zone_id" id="bulkZone" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/20 outline-none transition-all duration-200">
                            <option value="">Pilih zone...</option>
                            @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Records</label>
                        <div class="max-h-48 overflow-y-auto space-y-1.5 p-3 rounded-xl bg-gray-800/30 border border-gray-700/50" id="recordCheckboxes">
                            <p class="text-gray-600 text-xs py-2 text-center">Pilih zone dulu...</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">IP Baru</label>
                        <input type="text" name="ip_address" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-violet-500/50 focus:ring-1 focus:ring-violet-500/20 outline-none transition-all duration-200" placeholder="0.0.0.0">
                    </div>
                    <button type="submit" class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-violet-500 to-purple-500 text-white text-sm font-semibold hover:from-violet-400 hover:to-purple-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-violet-500/10">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Bulk Update
                    </button>
                </form>
            </div>
        </div>

        {{-- Quick Overview --}}
        @if($zones->isNotEmpty())
        <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-2.5">
                <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                <h2 class="text-sm font-semibold text-white">Quick Overview</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800/50">
                            <th class="text-left px-5 py-3.5 font-semibold">Zone</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Account</th>
                            <th class="text-right px-5 py-3.5 font-semibold">A Records</th>
                            <th class="text-right px-5 py-3.5 font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/30">
                        @foreach($zones as $zone)
                        <tr class="hover:bg-gray-800/20 transition-colors">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('zones.records', $zone) }}" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">{{ $zone->name }}</a>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $zone->cloudflareAccount->name }}</td>
                            <td class="px-5 py-3.5 text-right text-gray-300">{{ $zone->dnsRecords->where('type', 'A')->count() }}</td>
                            <td class="px-5 py-3.5 text-right text-gray-600">{{ $zone->dnsRecords->count() }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        document.getElementById('bulkZone')?.addEventListener('change', function() {
            const zoneId = this.value;
            const container = document.getElementById('recordCheckboxes');
            if (!zoneId) {
                container.innerHTML = '<p class="text-gray-600 text-xs py-2 text-center">Pilih zone dulu...</p>';
                return;
            }
            const zones = @json($zones);
            const zone = zones.find(z => z.id == zoneId);
            if (!zone || !zone.dns_records) {
                container.innerHTML = '<p class="text-gray-600 text-xs py-2 text-center">No records found.</p>';
                return;
            }
            const records = zone.dns_records.filter(r => r.type === 'A');
            if (records.length === 0) {
                container.innerHTML = '<p class="text-gray-600 text-xs py-2 text-center">No A records.</p>';
                return;
            }
            container.innerHTML = 
                '<label class="flex items-center gap-2 text-[11px] text-gray-400 pb-2 border-b border-gray-700/30 mb-1.5 cursor-pointer hover:text-gray-300 transition-colors">' +
                '<input type="checkbox" onchange="document.querySelectorAll(\'#recordCheckboxes input[type=checkbox]\').forEach(c => c.checked = this.checked)" class="rounded bg-gray-700 border-gray-600 text-violet-500 focus:ring-violet-500/20">' +
                '<span class="font-medium">Select All</span></label>' +
                records.map(r => 
                    '<label class="flex items-center gap-2 text-xs text-gray-400 py-1.5 cursor-pointer hover:text-gray-200 transition-colors rounded-lg hover:bg-gray-800/30 px-1.5">' +
                    '<input type="checkbox" name="record_ids[]" value="' + r.id + '" class="rounded bg-gray-700 border-gray-600 text-violet-500 focus:ring-violet-500/20">' +
                    '<span class="font-mono">' + r.name + '</span>' +
                    '<span class="text-gray-600">(' + r.content + ')</span></label>'
                ).join('');
        });
    </script>
    @endpush
</x-app-layout>
