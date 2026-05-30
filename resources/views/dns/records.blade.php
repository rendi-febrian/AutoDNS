<x-app-layout>
    <div class="max-w-6xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <a href="{{ route('dashboard') }}" class="p-2 rounded-xl bg-gray-800/50 text-gray-500 hover:text-gray-200 hover:bg-gray-700/50 transition-all">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-white tracking-tight">{{ $zone->name }}</h1>
                        <span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full font-medium {{ $zone->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                            <span class="w-1 h-1 rounded-full {{ $zone->status === 'active' ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                            {{ $zone->status }}
                        </span>
                    </div>
                    <p class="text-gray-500 text-sm mt-1">{{ $zone->cloudflareAccount->name }} · DNS Records</p>
                </div>
            </div>
            <form action="{{ route('zones.records.sync', $zone) }}" method="POST">
                @csrf
                <button type="submit" class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-300 text-sm font-medium hover:bg-gray-700/50 hover:text-white active:scale-[0.98] transition-all duration-200">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Sync
                </button>
            </form>
        </div>

        {{-- Create Record --}}
        <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-2.5">
                <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                <h2 class="text-sm font-semibold text-white">New DNS Record</h2>
            </div>
            <form action="{{ route('zones.records.create', $zone) }}" method="POST" class="p-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Type</label>
                        <select name="type" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200">
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="MX">MX</option>
                            <option value="TXT">TXT</option>
                            <option value="NS">NS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Name</label>
                        <input type="text" name="name" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200" placeholder="sub.domain.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Content</label>
                        <input type="text" name="content" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200" placeholder="IP or target">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">TTL</label>
                        <select name="ttl" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200">
                            <option value="120">Auto (120)</option>
                            <option value="60">1 min</option>
                            <option value="300">5 min</option>
                            <option value="3600">1 hour</option>
                            <option value="86400">24 hours</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-300 text-sm cursor-pointer hover:bg-gray-700/50 transition-all duration-200">
                            <input type="checkbox" name="proxied" value="1" class="rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500/20">
                            <span>Proxy</span>
                        </label>
                        <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                            Create
                        </button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Records Table --}}
        <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800/50">
                            <th class="text-left px-5 py-3.5 font-semibold">Type</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Name</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Content</th>
                            <th class="text-center px-5 py-3.5 font-semibold">Proxy</th>
                            <th class="text-center px-5 py-3.5 font-semibold">TTL</th>
                            <th class="text-center px-5 py-3.5 font-semibold">Auto-Sync</th>
                            <th class="text-right px-5 py-3.5 font-semibold">Synced</th>
                            <th class="text-right px-5 py-3.5 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/30">
                        @forelse($zone->dnsRecords as $record)
                        @php $isTracked = $record->trackedDomain()->exists(); @endphp
                        <tr class="hover:bg-gray-800/20 transition-colors" id="row-{{ $record->id }}">
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $record->type === 'A' ? 'bg-blue-500/10 text-blue-400' : ($record->type === 'CNAME' ? 'bg-purple-500/10 text-purple-400' : 'bg-gray-500/10 text-gray-400') }}">
                                    {{ $record->type }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-300 font-mono text-xs">{{ $record->name }}</td>
                            <td class="px-5 py-3.5 text-gray-400 font-mono text-xs max-w-[200px] truncate">{{ $record->content }}</td>
                            <td class="px-5 py-3.5 text-center">
                                @if($record->proxied)
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 font-medium">Proxied</span>
                                @else
                                    <span class="text-[11px] text-gray-600">DNS only</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center text-gray-500 text-xs">{{ $record->ttl === 120 ? 'Auto' : $record->ttl . 's' }}</td>
                            <td class="px-5 py-3.5 text-center">
                                @if($record->type === 'A')
                                    @if($isTracked)
                                    <span class="inline-flex items-center gap-1 text-[11px] text-emerald-400">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Tracked
                                    </span>
                                    @else
                                    <form action="{{ route('tracked-domains.store') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="domain_name" value="{{ $record->name }}">
                                        <input type="hidden" name="zone_name" value="{{ $zone->name }}">
                                        <input type="hidden" name="dns_record_id" value="{{ $record->id }}">
                                        <button type="submit" class="text-[11px] px-2.5 py-1 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 active:scale-[0.98] transition-all">
                                            + Track
                                        </button>
                                    </form>
                                    @endif
                                @else
                                    <span class="text-[11px] text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right text-gray-600 text-xs">{{ $record->synced_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <button onclick="openEdit({{ $record->id }})" class="text-[11px] px-2.5 py-1 rounded-lg bg-gray-800/50 text-gray-400 hover:text-white hover:bg-gray-700/50 active:scale-[0.98] transition-all">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-gray-500 text-sm">Belum ada DNS records. Sync atau buat baru.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="w-full max-w-lg mx-4 rounded-2xl bg-gray-900 border border-gray-700/50 shadow-2xl">
            <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Edit DNS Record</h3>
                <button onclick="closeEdit()" class="p-1.5 rounded-lg hover:bg-gray-800 text-gray-500 hover:text-gray-200 transition-all">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editForm" method="POST" class="p-5 space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Type</label>
                        <select name="type" id="editType" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800 border border-gray-700 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="MX">MX</option>
                            <option value="TXT">TXT</option>
                            <option value="NS">NS</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">TTL</label>
                        <select name="ttl" id="editTtl" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800 border border-gray-700 text-gray-200 text-sm focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all">
                            <option value="120">Auto (120)</option>
                            <option value="60">1 min</option>
                            <option value="300">5 min</option>
                            <option value="3600">1 hour</option>
                            <option value="86400">24 hours</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Name</label>
                    <input type="text" name="name" id="editName" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800 border border-gray-700 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Content</label>
                    <input type="text" name="content" id="editContent" required class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800 border border-gray-700 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all">
                </div>
                <label class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-gray-800 border border-gray-700 text-gray-300 text-sm cursor-pointer hover:bg-gray-800/80 transition-all">
                    <input type="checkbox" name="proxied" id="editProxied" value="1" class="rounded bg-gray-700 border-gray-600 text-blue-500 focus:ring-blue-500/20">
                    <span>Proxy through Cloudflare</span>
                </label>
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const records = @json($zone->dnsRecords);
        const baseRoute = '{{ url("/zones/records") }}';

        function openEdit(id) {
            const rec = records.find(r => r.id === id);
            if (!rec) return;
            document.getElementById('editForm').action = baseRoute + '/' + id;
            document.getElementById('editType').value = rec.type;
            document.getElementById('editName').value = rec.name;
            document.getElementById('editContent').value = rec.content;
            document.getElementById('editTtl').value = rec.ttl;
            document.getElementById('editProxied').checked = rec.proxied;
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }

        function closeEdit() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        document.getElementById('editModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeEdit();
        });
    </script>
    @endpush
</x-app-layout>
