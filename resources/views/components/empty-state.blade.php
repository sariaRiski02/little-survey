@props(['title', 'text' => null])
<div {{ $attributes->class('rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center') }}>
    <p class="text-lg font-bold text-ink">{{ $title }}</p>
    @if ($text)
        <p class="mx-auto mt-2 max-w-md text-slate-600">{{ $text }}</p>
    @endif
    @isset($action)
        <div class="mt-6 flex justify-center">{{ $action }}</div>
    @endisset
</div>
