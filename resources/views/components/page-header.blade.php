@props(['title', 'subtitle' => null])
<div {{ $attributes->class('mb-8 flex flex-wrap items-start justify-between gap-4') }}>
    <div>
        <h1 class="text-2xl font-extrabold text-ink sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-slate-600">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
