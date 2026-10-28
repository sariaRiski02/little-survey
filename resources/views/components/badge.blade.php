@props(['tone' => 'neutral'])
@php
    $kelas = [
        'neutral' => 'bg-slate-100 text-slate-700',
        'success' => 'bg-teal-50 text-teal-800',
        'warning' => 'bg-amber-50 text-amber-800',
        'info' => 'bg-sky-50 text-sky-800',
        'danger' => 'bg-rose-50 text-rose-800',
    ][$tone] ?? 'bg-slate-100 text-slate-700';
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-3 py-1 text-xs font-bold', $kelas]) }}>{{ $slot }}</span>
