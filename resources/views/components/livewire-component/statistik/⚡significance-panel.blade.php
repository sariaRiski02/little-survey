<?php

use App\Models\Project;
use App\Services\SignificanceService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function with(SignificanceService $service): array
    {
        return ['analysis' => $service->analyze($this->project)];
    }
};
?>

<section aria-labelledby="h-signifikansi" class="mt-8 rounded-2xl border border-slate-200 bg-white p-6" wire:poll.60s>
    <h2 id="h-signifikansi" class="text-lg font-extrabold">Uji signifikansi</h2>
    <p class="mt-1 text-sm text-slate-600">Menguji apakah perbedaan hasil antar kandidat nyata secara statistik atau hanya kebetulan sampel. Dihitung langsung dari jawaban yang sudah masuk, dan hasilnya tampil di Dashboard serta halaman hasil untuk tamu.</p>

    @if (! $analysis)
        <x-empty-state class="mt-5" title="Belum ada pertanyaan kandidat" text="Tambahkan pertanyaan bertipe “Pilih kandidat” di menu Pertanyaan." />
    @elseif ($analysis['n'] === 0)
        <x-empty-state class="mt-5" title="Belum ada jawaban" text="Uji signifikansi dihitung setelah responden mengisi survei." />
    @else
        <x-significance-result class="mt-5" :analysis="$analysis" :label="$project->usesWilayah() ? $project->wilayah_label : null" />
    @endif
</section>
