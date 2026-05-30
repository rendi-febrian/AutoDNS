@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-white bg-gradient-to-r from-blue-500/20 to-cyan-500/20 border border-blue-500/20 transition-all duration-150'
            : 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/50 transition-all duration-150';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
