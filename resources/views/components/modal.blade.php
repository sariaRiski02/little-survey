{{-- Pakai di dalam x-data yang punya properti boolean; $show = nama properti Alpine. --}}
@props(['show', 'title'])
<div x-cloak x-show="{{ $show }}" @keydown.escape.window="{{ $show }} = false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="absolute inset-0 bg-slate-900/40" @click="{{ $show }} = false"></div>
    <div x-show="{{ $show }}" x-transition class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h2 class="text-lg font-extrabold text-ink">{{ $title }}</h2>
        <div class="mt-3 text-slate-600">{{ $slot }}</div>
        @isset($footer)
            <div class="mt-6 flex flex-wrap justify-end gap-2">{{ $footer }}</div>
        @endisset
    </div>
</div>
