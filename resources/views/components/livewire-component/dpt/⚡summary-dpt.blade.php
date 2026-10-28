<?php

use App\Models\Project;
use App\Services\DptService;
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

    #[On('dpt-changed')]
    public function refreshSummary()
    {
        unset($this->summary);
    }

    #[Computed]
    public function summary(): array
    {
        return app(DptService::class)->summary($this->project);
    }
};
?>

<section aria-labelledby="h-ringkasan" class="mb-10">
    <h2 id="h-ringkasan" class="sr-only">Ringkasan</h2>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <x-stat-card class="col-span-2 sm:col-span-3 lg:col-span-1" label="Total DPT" :value="number_format($this->summary['total'], 0, ',', '.')" />
        @if ($project->usesWilayah())
            <div class="col-span-2 grid max-h-72 grid-cols-2 gap-4 overflow-y-auto sm:col-span-3 sm:grid-cols-3 lg:col-span-3" tabindex="0" aria-label="DPT per {{ $project->wilayah_label }}">
                @foreach ($this->summary['perWilayah'] as $wilayah)
                    <x-stat-card wire:key="wilayah-{{ $wilayah['nama'] }}" :label="$wilayah['nama']" :value="number_format($wilayah['jumlah'], 0, ',', '.')" />
                @endforeach
            </div>
        @endif
    </div>
    @if ($project->usesWilayah() && $this->summary['perWilayah']->isEmpty())
        <x-empty-state class="mt-4" :title="'Belum ada data per '.$project->wilayah_label" />
    @endif
</section>
