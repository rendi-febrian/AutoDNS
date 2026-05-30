<x-app-layout>
    <div class="max-w-6xl mx-auto space-y-8">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Activity Logs</h1>
                <p class="text-gray-500 text-sm mt-1">Riwayat update DNS record</p>
            </div>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800/50">
                            <th class="text-left px-5 py-3.5 font-semibold">Record</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Zone</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Type</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Old IP</th>
                            <th class="text-left px-5 py-3.5 font-semibold">New IP</th>
                            <th class="text-left px-5 py-3.5 font-semibold">Status</th>
                            <th class="text-left px-5 py-3.5 font-semibold hidden lg:table-cell">Message</th>
                            <th class="text-right px-5 py-3.5 font-semibold">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/30">
                        @forelse($logs as $log)
                        <tr class="hover:bg-gray-800/20 transition-colors">
                            <td class="px-5 py-3.5 text-gray-300 font-mono text-xs">{{ $log->record_name }}</td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs">{{ $log->zone_name }}</td>
                            <td class="px-5 py-3.5">
                                <span class="text-[11px] px-2 py-0.5 rounded-md bg-gray-800/50 text-gray-400 font-medium">{{ $log->record_type }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 font-mono text-xs">{{ $log->old_ip ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-gray-300 font-mono text-xs">{{ $log->new_ip }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full font-medium {{ $log->status === 'success' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                                    <span class="w-1 h-1 rounded-full {{ $log->status === 'success' ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 text-xs max-w-[150px] truncate hidden lg:table-cell">{{ $log->response_message ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-right text-gray-600 text-xs">{{ $log->created_at->format('d M H:i') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-gray-500 text-sm">Belum ada aktivitas.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($logs->hasPages())
        <div class="flex justify-center">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
