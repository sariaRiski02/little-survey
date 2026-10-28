<?php

use App\Models\Project;
use App\Services\RespondentService;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $template = '';

    public function mount(Project $project, RespondentService $service)
    {
        $this->project = $project;
        $this->template = $service->invitationTemplate($project);
    }

    public function save(RespondentService $service)
    {
        $service->saveInvitationTemplate($this->project, $this->template);
        $this->template = $service->invitationTemplate($this->project);

        $this->dispatch('respondent-changed');
        $this->dispatch('template-saved');
    }
};
?>

<div @template-saved.window="tmpl = false">
    <x-modal show="tmpl" title="Template pesan undangan">
        <div class="space-y-1.5">
            <label for="tpl" class="block text-sm font-semibold text-ink">Isi pesan</label>
            <textarea id="tpl" rows="5" wire:model="template" aria-describedby="tpl-help" class="w-full rounded-2xl border border-slate-300 bg-white p-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
            <p id="tpl-help" class="text-xs">Gunakan <code>{nama}</code> dan <code>{tautan}</code> sebagai penanda.</p>
            @error('template') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
        </div>
        <x-slot:footer>
            <button type="button" @click="tmpl = false" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Batal</button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-50">Simpan</button>
        </x-slot:footer>
    </x-modal>
</div>
