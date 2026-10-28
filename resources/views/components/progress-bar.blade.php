@props(['value' => 0, 'label' => 'Progres', 'color' => '#0F6B6B'])
<div {{ $attributes->class('h-2.5 w-full overflow-hidden rounded-full bg-slate-200') }}
     role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($value) }}">
    <div class="h-full rounded-full" style="width: {{ min(100, max(0, $value)) }}%; background-color: {{ $color }}"></div>
</div>
