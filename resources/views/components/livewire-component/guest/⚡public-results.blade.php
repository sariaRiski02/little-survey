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
        $candidates = $this->project->Candidate()->orderByRaw('number is null')->orderBy('number')->orderBy('name')->get();

        if (! $this->project->resultsArePublished()) {
            return ['published' => false, 'candidates' => $candidates];
        }

        return [
            'published' => true,
            'candidates' => $candidates,
            'summary' => $service->summary($this->project),
            'progress' => $service->progress($this->project),
            'results' => $service->results($this->project, $this->weighted && $this->view === 'mentah'),
            'analysis' => $this->view === 'uji' ? $significance->analyze($this->project) : null,
            'updatedAt' => $service->lastFilledAt($this->project) ?? $this->project->results_published_at,
            // A wilayah needs this many answers before its result is shown, so single votes are not exposed.
            'minWilayahN' => 5,
        ];
    }
};
?>

@php
    $fmt = fn ($value) => number_format($value, 1, ',', '.');
    $n = fn ($value) => number_format($value, 0, ',', '.');
    $label = $project->wilayah_label;
    $usesWilayah = $project->usesWilayah();
    $galat = $results['galat'] ?? null;
@endphp

<div>
    <header class="mb-8">
        <p class="text-sm font-bold text-accent">Hasil survei</p>
        <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">{{ $project->name }}</h1>
        @if ($project->description)<p class="mt-2 text-slate-600">{{ $project->description }}</p>@endif
    </header>

    <section aria-labelledby="h-daftar-kandidat" class="mb-8">
        <h2 id="h-daftar-kandidat" class="mb-4 text-lg font-extrabold">Kandidat</h2>
        @if ($candidates->isEmpty())
            <x-empty-state title="Belum ada kandidat" text="Daftar kandidat akan ditampilkan setelah penyelenggara menambahkannya." />
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($candidates as $candidate)
                    @php
                        $name = ucwords($candidate->name);
                        $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                    @endphp
                    <li wire:key="daftar-kandidat-{{ $candidate->id }}" class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                        @if ($candidate->picture)
                            <img src="{{ asset('storage/'.$candidate->picture) }}" alt="Foto {{ $name }}" class="h-14 w-14 shrink-0 rounded-2xl object-cover">
                        @else
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold text-white" style="background-color: {{ $candidate->color ?: '#64748B' }}" aria-hidden="true">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate font-bold">{{ $name }}</p>
                            <p class="flex items-center gap-2 text-sm text-slate-600">
                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $candidate->color ?: '#64748B' }}" aria-hidden="true"></span>
                                {{ $candidate->number ? 'Nomor urut '.$candidate->number : 'Tanpa nomor urut' }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if (! $published)
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-6 text-amber-900" role="status">
            <h2 class="text-lg font-extrabold">Survei masih berlangsung</h2>
            <p class="mt-1">Hasil belum dapat ditampilkan. Hasil akan tersedia setelah seluruh proses survei selesai dan dipublikasikan oleh penyelenggara.</p>
        </div>
    @else
        @if (! $project->isFullDptMode() && ! $project->is_random_sample)
            <div role="alert" class="mb-8 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                Sampel non-acak: hasil bersifat gambaran, bukan estimasi populasi.
            </div>
        @endif

        <section aria-labelledby="h-ringkasan" class="mb-8">
            <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
            <div class="grid grid-cols-2 gap-4">
                <x-stat-card label="Responden yang mengisi" :value="$n($summary['filled'])" :hint="'dari '.$n($summary['respondents']).' responden'" />
                <x-stat-card label="Tingkat respons" :value="$fmt($summary['responseRate']).'%'" />
            </div>
        </section>

        <section aria-labelledby="h-kandidat" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 id="h-kandidat" class="text-lg font-extrabold">Perbandingan kandidat</h2>
                <div class="inline-flex rounded-2xl bg-slate-100 p-1" role="group" aria-label="Jenis hasil kandidat">
                <button type="button" wire:click="setView('mentah')" aria-pressed="{{ $view === 'mentah' ? 'true' : 'false' }}"
                        class="{{ $view === 'mentah' ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Hasil mentah</button>
                <button type="button" wire:click="setView('uji')" aria-pressed="{{ $view === 'uji' ? 'true' : 'false' }}"
                        class="{{ $view === 'uji' ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Hasil uji signifikansi</button>
            </div>
            </div>
            <div class="mt-3 flex justify-end">
                @if ($view === 'mentah' && $results && $results['canWeight'])
                    <div class="inline-flex rounded-2xl bg-slate-100 p-1" role="group" aria-label="Jenis hasil">
                        <button type="button" wire:click="$set('weighted', false)" aria-pressed="{{ $weighted ? 'false' : 'true' }}"
                                class="{{ ! $weighted ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Mentah</button>
                        <button type="button" wire:click="$set('weighted', true)" aria-pressed="{{ $weighted ? 'true' : 'false' }}"
                                class="{{ $weighted ? 'bg-white shadow-sm' : '' }} min-h-11 rounded-xl px-4 text-sm font-bold focus-visible:outline-2 focus-visible:outline-accent">Tertimbang</button>
                    </div>
                @endif
            </div>

            @if (! $results || $results['n'] === 0)
                <x-empty-state class="mt-6" title="Belum ada data yang dapat ditampilkan" />
            @elseif ($view === 'uji' && $analysis)
                <x-significance-result class="mt-6" :analysis="$analysis" :label="$usesWilayah ? $label : null" />
            @else
                @php
                    $top = collect($results['candidates'])->max('persen');
                    $scale = max(60, ceil(($top + $galat) / 10) * 10);
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

                <p class="mt-6 rounded-2xl p-4 text-sm font-bold {{ $results['unggul'] ? 'bg-teal-50 text-teal-900' : 'bg-amber-50 text-amber-900' }}">
                    {{ $results['unggul'] ? $results['unggul'].' unggul' : 'Selisih masih dalam batas galat, belum bisa disimpulkan' }}
                    <span class="font-normal">(selisih teratas {{ $fmt($results['selisih']) }} poin)</span>
                </p>
            @endif
        </section>

        @if ($usesWilayah && count($progress))
            <section aria-labelledby="h-progres" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 id="h-progres" class="text-lg font-extrabold">Progres pengisian per {{ $label }}</h2>
                <ul class="mt-6 grid max-h-[28rem] gap-x-6 gap-y-5 overflow-y-auto pr-2 sm:grid-cols-2" tabindex="0" aria-label="Daftar progres {{ $label }}">
                    @foreach ($progress as $row)
                        <li wire:key="progres-{{ $row['nama'] }}">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="font-bold">{{ $row['nama'] }}</span>
                                <span class="text-sm font-bold">{{ $fmt($row['persen']) }}%</span>
                            </div>
                            <p class="mb-2 text-sm text-slate-600">terisi {{ $n($row['terisi']) }} dari {{ $n($row['kuota']) }}</p>
                            <x-progress-bar :value="$row['persen']" :label="'Progres '.$row['nama']" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($usesWilayah && $results && $results['n'] > 0 && count($results['perWilayah']))
            @php $leader = collect($results['candidates'])->sortByDesc('persen')->first()['nama']; @endphp
            <section aria-labelledby="h-wilayah" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 id="h-wilayah" class="text-lg font-extrabold">Suara {{ $leader }} per {{ $label }}</h2>
                <p class="mt-1 text-sm text-slate-600">Hasil mentah. Angka dengan responden kurang dari {{ $minWilayahN }} tidak ditampilkan, dan angka per {{ $label }} kurang dapat diandalkan.</p>
                <ul class="mt-4 grid max-h-72 gap-3 overflow-y-auto pr-2 sm:grid-cols-2" tabindex="0" aria-label="Hasil per {{ $label }}">
                    @foreach ($results['perWilayah'] as $row)
                        <li wire:key="wilayah-{{ $row['nama'] }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                            <span class="font-semibold">{{ $row['nama'] }}</span>
                            @if ($row['persen'] === null || $row['n'] < $minWilayahN)
                                <span class="text-slate-600">-</span>
                            @else
                                <span class="font-extrabold">{{ $fmt($row['persen']) }}%</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="h-metode" class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 id="h-metode" class="text-lg font-extrabold">Catatan metodologi</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Tingkat kepercayaan</dt><dd class="font-bold">{{ $results['kepercayaan'] ?? $project->sample_confidence }}%</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Margin of error</dt><dd class="font-bold">{{ $galat !== null ? '±'.$fmt($galat).'%' : '-' }} <span class="font-normal text-slate-600">(target ±{{ $fmt($project->sample_margin_error) }}%)</span></dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Jumlah jawaban dianalisis</dt><dd class="font-bold">{{ $results ? $n($results['n']) : '-' }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs text-slate-600">Metode sampel</dt><dd class="font-bold">{{ ! $project->isFullDptMode() && ! $project->is_random_sample ? 'Non-acak' : 'Acak' }}</dd></div>
                <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2"><dt class="text-xs text-slate-600">Data diperbarui</dt><dd class="font-bold">{{ $updatedAt->format('d/m/Y H:i') }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-slate-600">Margin of error dihitung dengan proporsi 0,5 dan koreksi populasi terbatas. Halaman ini tidak menampilkan jawaban individual maupun identitas responden.</p>
        </section>
    @endif
</div>
