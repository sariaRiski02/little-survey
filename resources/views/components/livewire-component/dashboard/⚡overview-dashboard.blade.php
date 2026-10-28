<?php

use App\Models\Project;
use App\Services\DashboardService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function with(DashboardService $service): array
    {
        return [
            'summary' => $service->summary($this->project),
            'progress' => $service->progress($this->project),
        ];
    }
};
?>

@php
    $n = fn ($value) => number_format($value, 0, ',', '.');
    $fmt = fn ($value) => number_format($value, 1, ',', '.');
    $usesWilayah = $project->usesWilayah();
    $label = $project->wilayah_label;
@endphp

<div wire:poll.30s>
    <section aria-labelledby="h-ringkasan" class="mb-10">
        <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @if (! $project->isFullDptMode())
                <x-stat-card label="Perkiraan populasi" :value="$summary['population'] !== null ? $n($summary['population']) : '-'" hint="Opsional, diisi di Pengaturan" />
            @else
                <x-stat-card label="Jumlah DPT" :value="$n($summary['population'])" />
            @endif
            <x-stat-card label="Jumlah responden" :value="$n($summary['respondents'])" />
            <x-stat-card label="Jumlah kandidat" :value="$n($summary['candidates'])" />
            <x-stat-card label="Sudah mengisi" :value="$n($summary['filled'])" />
            <x-stat-card label="Belum mengisi" :value="$n($summary['notFilled'])" />
            <x-stat-card label="Tingkat respons" :value="$fmt($summary['responseRate']).'%'" />
        </div>
    </section>

    <section aria-labelledby="h-progres" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
        @if (! $usesWilayah)
            <h2 id="h-progres" class="text-lg font-extrabold">Progres pengisian</h2>
            <div class="mt-6">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="font-bold">Total</span>
                    <span class="text-sm font-bold">{{ $fmt($progress[0]['persen']) }}%</span>
                </div>
                <p class="mb-2 text-sm text-slate-600">terisi {{ $n($progress[0]['terisi']) }} dari {{ $n($progress[0]['kuota']) }} responden</p>
                <x-progress-bar :value="$progress[0]['persen']" label="Progres pengisian total" />
            </div>
        @else
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="h-progres" class="text-lg font-extrabold">Progres {{ $label }}</h2>
                <p class="text-sm text-slate-600">{{ count($progress) }} {{ $label }}</p>
            </div>
            @if (count($progress))
                <ul class="mt-6 grid max-h-[28rem] gap-x-6 gap-y-5 overflow-y-auto pr-2 sm:grid-cols-2 lg:grid-cols-3" tabindex="0" aria-label="Daftar progres {{ $label }}">
                    @foreach ($progress as $row)
                        <li wire:key="progres-{{ $row['nama'] }}">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="font-bold">{{ $row['nama'] }}</span>
                                <span class="text-sm font-bold">{{ $fmt($row['persen']) }}%</span>
                            </div>
                            <p class="mb-2 text-sm text-slate-600">terisi {{ $n($row['terisi']) }} dari kuota {{ $n($row['kuota']) }}</p>
                            <x-progress-bar :value="$row['persen']" :label="'Progres '.$row['nama']" />
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state class="mt-6" :title="'Belum ada responden per '.$label" />
            @endif
        @endif
    </section>
</div>
