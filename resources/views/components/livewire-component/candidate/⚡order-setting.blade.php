<?php

use App\Models\Project;
use App\Services\CandidateService;
use Livewire\Component;

new class extends Component
{
    public Project $project;
    public bool $randomize = false;

    public function mount(Project $project)
    {
        $this->project = $project;
        $this->randomize = (bool) $project->randomize_candidates;
    }

    public function toggle(CandidateService $service)
    {
        $service->setRandomOrder($this->project, ! $this->randomize);
        $this->randomize = (bool) $this->project->randomize_candidates;
    }
};
?>

<section aria-labelledby="h-acak" class="rounded-2xl border border-slate-200 bg-white p-6">
    <h2 id="h-acak" class="sr-only">Pengaturan urutan</h2>
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="font-bold" id="lbl-acak">Acak urutan kandidat untuk tiap responden</p>
            <p class="text-sm text-slate-600">Mengurangi bias karena kandidat yang tampil paling atas.</p>
            @error('project')
                <p class="mt-2 text-sm text-amber-900" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <button type="button" role="switch" aria-checked="{{ $randomize ? 'true' : 'false' }}" aria-labelledby="lbl-acak"
            wire:click="toggle" :disabled="terkunci"
            class="{{ $randomize ? 'bg-accent' : 'bg-slate-300' }} relative inline-flex h-11 w-16 shrink-0 items-center rounded-full px-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">
            <span class="{{ $randomize ? 'translate-x-6' : 'translate-x-0' }} block h-8 w-8 rounded-full bg-white shadow transition-transform"></span>
        </button>
    </div>
</section>
