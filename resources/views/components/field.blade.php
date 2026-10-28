@props(['label', 'name', 'type' => 'text', 'help' => null, 'value' => null])
@php
    $id = 'f-'.$name;

@endphp
<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-semibold text-ink">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
           @if($help) aria-describedby="{{ $id }}-help" @endif
           {{ $attributes->class('min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent') }}>
    @if ($help)
        <p id="{{ $id }}-help" class="text-xs text-slate-600">{{ $help }}</p>
    @endif
    {{-- TODO(backend): tampilkan pesan galat validasi server di sini. --}}
</div>
