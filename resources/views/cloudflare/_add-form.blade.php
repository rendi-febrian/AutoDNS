<div class="rounded-2xl bg-gray-900/30 border border-gray-800/50 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-800/50 flex items-center gap-3">
        <div class="p-2 rounded-xl bg-gradient-to-br from-blue-500/20 to-cyan-500/10">
            <svg class="w-4 h-4 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">Tambah Akun Cloudflare</h2>
            <p class="text-[11px] text-gray-500">Masukkan API Token, nama & email otomatis dari Cloudflare</p>
        </div>
    </div>
    <form action="{{ route('cloudflare.accounts.store') }}" method="POST" class="p-5">
        @csrf
        <div class="flex items-end gap-3 max-w-2xl">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-400 mb-1.5">API Token</label>
                <input type="password" name="api_token" value="{{ old('api_token') }}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-gray-800/50 border border-gray-700/50 text-gray-200 text-sm placeholder-gray-600 font-mono focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/20 outline-none transition-all duration-200"
                       placeholder="cf_...">
            </div>
            <button type="submit" class="flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-sm font-semibold hover:from-blue-400 hover:to-cyan-400 active:scale-[0.98] transition-all duration-200 shadow-lg shadow-blue-500/10">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Verifikasi
            </button>
        </div>
        <p class="text-xs text-gray-600 mt-2">
            Buat token di <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" class="text-blue-400 hover:text-blue-300">Cloudflare Dashboard → API Tokens</a>
        </p>
    </form>
</div>
