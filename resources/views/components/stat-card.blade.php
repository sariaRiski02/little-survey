@props(['label', 'value', 'hint' => null])
<div {{ $attributes->class('rounded-2xl border border-slate-200 bg-white p-5') }}>
    <p class="text-sm font-semibold text-slate-600">{{ $label }}</p>
    <p class="mt-2 text-3xl font-extrabold text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-600">{{ $hint }}</p>
    @endif
</div>
