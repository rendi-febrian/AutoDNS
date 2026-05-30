<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'AutoDNS') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-950 text-gray-100 min-h-screen flex flex-col">
        {{-- Background decoration --}}
        <div class="fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-blue-500/5 via-cyan-500/5 to-transparent blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-to-tr from-violet-500/5 via-purple-500/5 to-transparent blur-3xl"></div>
        </div>

        <div class="flex-1 flex flex-col items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                {{-- Logo --}}
                <div class="flex justify-center mb-8">
                    <a href="/" class="flex items-center gap-3">
                        <div class="relative w-10 h-10">
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-500 to-cyan-400 rounded-xl blur-sm opacity-60"></div>
                            <div class="relative w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                            </div>
                        </div>
                        <div>
                            <span class="text-xl font-bold text-white tracking-tight">AutoDNS</span>
                            <span class="block text-[11px] text-gray-500 tracking-wider uppercase">Management</span>
                        </div>
                    </a>
                </div>

                {{-- Card --}}
                <div class="bg-gray-900/70 backdrop-blur-xl border border-gray-800/50 rounded-2xl shadow-2xl shadow-black/20">
                    <div class="p-6 lg:p-8">
                        {{ $slot }}
                    </div>
                </div>

                {{-- Footer --}}
                <p class="text-center text-xs text-gray-600 mt-6">
                    AutoDNS Dashboard &mdash; Cloudflare DNS Auto-Sync
                </p>
            </div>
        </div>
    </body>
</html>
