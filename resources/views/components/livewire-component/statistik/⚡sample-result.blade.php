<?php

use App\Models\Project;
use App\Services\StatistikService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    #[On('sample-params-saved')]
    public function refreshResult()
    {
        unset($this->calculation, $this->allocation);
    }

    #[Computed]
    public function calculation(): ?array
    {
        $service = app(StatistikService::class);

        return $service->calculate($service->population($this->project), $service->parameters($this->project));
    }

    #[Computed]
    public function allocation(): ?array
    {
        if (! $this->project->isFullDptMode() || ! $this->project->usesWilayah() || ! $this->calculation) {
            return null;
        }

        return app(StatistikService::class)->allocation($this->project, $this->calculation);
    }
};
?>

@php
    $n = fn ($value) => number_format($value, 0, ',', '.');
    $calculation = $this->calculation;
    $allocation = $this->allocation;
    $label = $project->wilayah_label;
@endphp

<div>
    <section aria-labelledby="h-hasil" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
        <h2 id="h-hasil" class="text-lg font-extrabold">Hasil hitung</h2>
        @if ($calculation)
            <p class="mt-1 text-sm text-slate-600">Rumus Cochran dengan koreksi populasi terbatas.</p>
            <ol class="mt-5 space-y-3">
                @foreach ($calculation['steps'] as $i => $step)
                    <li wire:key="langkah-{{ $i }}" class="flex gap-4 rounded-2xl bg-slate-50 p-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-extrabold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">{{ $step['judul'] }}</p>
                            <p class="break-words text-sm text-slate-600">{{ $step['rumus'] }}</p>
                        </div>
                        <p class="text-lg font-extrabold">{{ $step['hasil'] }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <x-stat-card label="Ukuran sampel" :value="$n($calculation['sampleSize'])" hint="responden yang dibutuhkan" />
                <x-stat-card label="Jumlah undangan" :value="$n($calculation['invitations'])" hint="termasuk cadangan non-respons" />
            </div>
        @elseif ($project->isFullDptMode())
            <x-empty-state class="mt-5" title="Belum ada data DPT" text="Impor DPT terlebih dahulu untuk menghitung ukuran sampel." />
        @else
            <x-empty-state class="mt-5" title="Populasi belum diisi" text="Isi populasi pada parameter lalu tekan Hitung." />
        @endif
    </section>

    @if ($project->isFullDptMode() && $calculation)
        @if (! $project->usesWilayah())
            <div class="mb-8 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900" role="note">
                Pengundian acak sederhana dari seluruh daftar.
            </div>
        @else
            <section aria-labelledby="h-alokasi" class="mb-8 rounded-2xl border border-slate-200 bg-white p-6">
                <h2 id="h-alokasi" class="text-lg font-extrabold">Alokasi per {{ $label }}</h2>
                <div class="mt-5 max-h-[32rem] overflow-auto rounded-2xl border border-slate-200" tabindex="0" aria-label="Tabel alokasi per {{ $label }}">
                    <table class="w-full min-w-[34rem] text-left text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-slate-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-bold">{{ $label }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Jumlah DPT</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Persentase</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Kuota sampel</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Kuota undangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($allocation['rows'] as $row)
                                <tr wire:key="alokasi-{{ $row['key'] ?? 'kosong' }}">
                                    <th scope="row" class="px-4 py-3 font-semibold">{{ $row['nama'] }}</th>
                                    <td class="px-4 py-3 text-right">{{ $n($row['dpt']) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row['persen'], 1, ',', '.') }}%</td>
                                    <td class="px-4 py-3 text-right">{{ $n($row['sampel']) }}</td>
                                    <td class="px-4 py-3 text-right">{{ $n($row['undangan']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 font-extrabold">
                            <tr>
                                <th scope="row" class="px-4 py-3">Total</th>
                                <td class="px-4 py-3 text-right">{{ $n($allocation['total']['dpt']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($allocation['total']['persen'], 1, ',', '.') }}%</td>
                                <td class="px-4 py-3 text-right">{{ $n($allocation['total']['sampel']) }}</td>
                                <td class="px-4 py-3 text-right">{{ $n($allocation['total']['undangan']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-3 text-xs text-slate-600">Kuota undangan dibulatkan ke atas per {{ $label }}, sehingga totalnya bisa sedikit lebih besar dari jumlah undangan di atas.</p>
            </section>
        @endif
    @endif
</div>
