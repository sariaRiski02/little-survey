<?php

use App\Models\Project;
use App\Services\DashboardService;
use App\Services\SignificanceService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public bool $weighted = false;

    /** 'mentah' shows the raw results, 'uji' the results with significance tests. */
    public string $view = 'mentah';

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function setView(string $view)
    {
        $this->view = $view === 'uji' ? 'uji' : 'mentah';
    }

    public function with(DashboardService $service, SignificanceService $significance): array
    {
        return [
            'results' => $service->results($this->project, $this->weighted && $this->view === 'mentah'),
            'analysis' => $this->view === 'uji' ? $significance->analyze($this->project) : null,
        ];
    }
};
?>

@php
    $fmt = fn ($value) => number_format($value, 1, ',', '.');
    $usesWilayah = $project->usesWilayah();
    $label = $project->wilayah_label;
    $scaleMax = 60; // right edge of the bar axis (%), display only
    $galat = $results['galat'] ?? null;
@endphp

<div wire:poll.30s class="space-y-8">
    <section aria-labelledby="h-kandidat" class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="h-kandidat" class="text-lg font-extrabold">Perbandingan kandidat</h2>
            <div class="inline-flex rounded-2xl bg-slate-100 p-1" role="group" aria-label="Jenis hasil kandidat">
                <button type="button" wire:click="setView('mentah')" aria-pressed="{{ $view === 'mentah' ? 'true' : 'false' }}"
                        class="{{ $view === 'mentah' ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Hasil mentah</button>
                <button type="button" wire:click="setView('uji')" aria-pressed="{{ $view === 'uji' ? 'true' : 'false' }}"
                        class="{{ $view === 'uji' ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Hasil uji signifikansi</button>
            </div>
        </div>

        @if (! $results)
            <x-empty-state class="mt-6" title="Belum ada pertanyaan kandidat" text="Tambahkan pertanyaan bertipe “Pilih kandidat” di menu Pertanyaan." />
        @elseif ($results['n'] === 0)
            <x-empty-state class="mt-6" title="Belum ada jawaban" text="Hasil muncul setelah responden mengisi survei." />
        @elseif ($view === 'uji' && $analysis)
            <x-significance-result class="mt-6" :analysis="$analysis" :label="$usesWilayah ? $label : null" />
        @else
            @php
                $top = collect($results['candidates'])->max('persen');
                $scale = max($scaleMax, ceil(($top + $galat) / 10) * 10);
            @endphp
            <p class="mt-1 text-sm text-slate-600">{{ $results['weighted'] ? 'Hasil tertimbang menurut jumlah DPT per '.$label.'.' : 'Hasil mentah.' }}</p>
            <ul class="mt-6 space-y-6">
                @foreach ($results['candidates'] as $candidate)
                    @php
                        $left = max(0, $candidate['persen'] - $galat) / $scale * 100;
                        $width = (min($scale, $candidate['persen'] + $galat) - max(0, $candidate['persen'] - $galat)) / $scale * 100;
                    @endphp
                    <li wire:key="kandidat-{{ $candidate['nama'] }}">
                        <div class="mb-1 flex items-baseline justify-between gap-3">
                            <span class="flex items-center gap-2 font-bold">
                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $candidate['warna'] }}" aria-hidden="true"></span>{{ $candidate['nama'] }}
                            </span>
                            <span class="text-sm font-bold">{{ $fmt($candidate['persen']) }}% <span class="font-normal text-slate-600">(±{{ $fmt($galat) }})</span></span>
                        </div>
                        <div class="relative h-6 rounded-full bg-slate-100" role="img" aria-label="{{ $candidate['nama'] }} {{ $fmt($candidate['persen']) }} persen, galat plus minus {{ $fmt($galat) }}">
                            <div class="h-full rounded-full" style="width: {{ $candidate['persen'] / $scale * 100 }}%; background-color: {{ $candidate['warna'] }}"></div>
                            <div class="absolute top-1/2 flex h-3 -translate-y-1/2 items-center" style="left: {{ $left }}%; width: {{ $width }}%">
                                <span class="block h-3 w-0.5 bg-ink"></span>
                                <span class="block h-0.5 flex-1 bg-ink"></span>
                                <span class="block h-3 w-0.5 bg-ink"></span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if (count($results['others']))
                <div class="mt-6 space-y-3 border-t border-slate-200 pt-5">
                    <p class="text-sm text-slate-600">Di luar kandidat:</p>
                    @foreach ($results['others'] as $other)
                        <div>
                            <div class="mb-1 flex justify-between text-sm"><span>{{ $other['nama'] }}</span><span class="font-bold">{{ $fmt($other['persen']) }}%</span></div>
                            <x-progress-bar :value="$other['persen'] / $scale * 100" color="#94A3B8" :label="$other['nama']" />
                        </div>
                    @endforeach
                </div>
            @endif

            <dl class="mt-6 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-600">n</dt><dd class="font-extrabold">{{ number_format($results['n'], 0, ',', '.') }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-600">Kepercayaan</dt><dd class="font-extrabold">{{ $results['kepercayaan'] }}%</dd></div>
                <div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-600">Selisih teratas</dt><dd class="font-extrabold">{{ $fmt($results['selisih']) }} poin</dd></div>
            </dl>
            <p class="mt-4 rounded-2xl p-4 text-sm font-bold {{ $results['unggul'] ? 'bg-teal-50 text-teal-900' : 'bg-amber-50 text-amber-900' }}">
                {{ $results['unggul'] ? $results['unggul'].' unggul' : 'Selisih masih dalam batas galat, belum bisa disimpulkan' }}
            </p>
        @endif
    </section>

    <section aria-labelledby="h-kualitas" class="rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="h-kualitas" class="text-lg font-extrabold">Kualitas data</h2>
            @if ($view === 'mentah' && $results && $results['canWeight'])
                <div class="inline-flex rounded-2xl bg-slate-100 p-1" role="group" aria-label="Jenis hasil">
                    <button type="button" wire:click="$set('weighted', false)" aria-pressed="{{ $weighted ? 'false' : 'true' }}"
                            class="{{ ! $weighted ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Mentah</button>
                    <button type="button" wire:click="$set('weighted', true)" aria-pressed="{{ $weighted ? 'true' : 'false' }}"
                            class="{{ $weighted ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Tertimbang</button>
                </div>
            @endif
        </div>

        @if ($results && $galat !== null)
            <div class="mt-6 rounded-2xl bg-slate-50 p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm text-slate-600">Margin of error aktual dibanding target</p>
                    <p class="font-extrabold">±{{ $fmt($galat) }}% <span class="text-sm font-normal text-slate-600">dari target ±{{ $fmt($results['galatTarget']) }}%</span></p>
                </div>
                <x-progress-bar class="mt-3" :value="$galat / $results['galatTarget'] * 100" :color="$galat <= $results['galatTarget'] ? '#0F6B6B' : '#E11D48'" label="Galat aktual terhadap target" />
                <p class="mt-2"><x-badge :tone="$galat <= $results['galatTarget'] ? 'success' : 'danger'">{{ $galat <= $results['galatTarget'] ? 'Memenuhi target' : 'Belum memenuhi target' }}</x-badge></p>
            </div>

            @if ($usesWilayah && count($results['perWilayah']))
                @php $leader = collect($results['candidates'])->sortByDesc('persen')->first()['nama']; @endphp
                <p class="mt-6 text-sm text-slate-600">Suara {{ $leader }} per {{ $label }} (hasil mentah). Dengan {{ $label }} banyak, angka per {{ $label }} kurang dapat diandalkan.</p>
                <ul class="mt-3 grid max-h-72 gap-3 overflow-y-auto pr-2 sm:grid-cols-2 lg:grid-cols-3" tabindex="0" aria-label="Hasil per {{ $label }}">
                    @foreach ($results['perWilayah'] as $row)
                        <li wire:key="wilayah-{{ $row['nama'] }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                            <span class="font-semibold">{{ $row['nama'] }}</span>
                            @if ($row['persen'] === null)
                                <span class="text-slate-600">-</span>
                            @else
                                <span class="font-extrabold">{{ $fmt($row['persen']) }}%</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @else
            <p class="mt-4 text-sm text-slate-600">Margin of error aktual dihitung setelah ada responden yang menjawab.</p>
        @endif
    </section>
</div>
