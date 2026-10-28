<?php

use App\Models\Candidate;
use App\Models\Project;
use App\Services\CandidateService;
use Livewire\Attributes\On;
use Livewire\Component;


new class extends Component
{
    public Project $project;
    public function mount(Project $project){
        $this->project = $project;
    }

    #[On('candidate-added')]
    public function refreshList(){
        $this->project->refresh();
    }

    public function delete($id)
    {
        Candidate::find($id)->delete();
        $this->project->refresh();
    }

    public function update($dataCandidate){
        dd($dataCandidate);
        // Candidate::find($dataCandidate['updateId'])->update([]);
    }

};
?>
<section aria-labelledby="h-daftar">
    <h2 id="h-daftar" class="mb-4 text-lg font-extrabold">Daftar kandidat ({{ $project->candidate->count() }})</h2>
    <ul class="grid gap-4 sm:grid-cols-2">
        @foreach($project->candidate as $candidate)
        <li class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-center gap-4">
                @if ($candidate->picture)
                    <img src="{{ asset('storage/'.$candidate->picture) }}" alt="{{ $candidate->name }}" class="h-14 w-14 shrink-0 rounded-2xl object-cover">

                @else
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold text-white" style="background-color: {{ $candidate->color }}" aria-hidden="true">AS</span>
                @endif
                <div class="min-w-0">
                    <p class="truncate font-bold">{{ ucfirst($candidate->name) }}</p>
                    <p class="flex items-center gap-2 text-sm text-slate-600">
                        <span class="h-3 w-3 rounded-full" style="background-color: {{ $candidate->color }}" aria-hidden="true"></span>
                        @if ($candidate->number)
                            Nomor urut {{ $candidate->number }}
                        @endif
                    </p>
                </div>
            </div>
            <div class="mt-5 flex gap-2">
                <button type="button" :disabled="terkunci"
                    @click="edit = {

                                    name: '{{ $candidate->name }}',
                                    number: {{ $candidate->number ?? null }},
                                    color: '{{ $candidate->color ?? null }}',
                                    picture: '{{ $candidate->picture ?? null }}',
                                    id: '{{ $candidate->id }}'

                                }; ubah = true;"
                            class="min-h-11 flex-1 rounded-2xl border border-slate-300 px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">Ubah</button>
                <button type="button" :disabled="terkunci"
                    @click="hapusNama = '{{ $candidate->name }}';
                            hapusId = '{{ $candidate->id }}';
                            hapus = true;
                            "
                    class="min-h-11 flex-1 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50">Hapus</button>
            </div>
        </li>
        @endforeach

        <x-modal show="hapus" title="Hapus kandidat?">
            Kandidat <strong x-text="hapusNama"></strong> akan dihapus dari daftar. Tindakan ini tidak dapat dibatalkan.
            <x-slot:footer>
                <button type="button" @click="hapus = false" class="min-h-11 rounded-2xl border border-slate-300 px-4 font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent">Batal</button>
                <button type="button" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700"

                    @click="$wire.delete(hapusId); hapus = false"
                >
                    Ya, hapus
                </button>
            </x-slot:footer>
        </x-modal>

        <x-modal show="ubah" title="Ubah kandidat">
            <div class="space-y-4">
                <x-field label="Nama" name="ubah-nama" x-model="edit.name" />
                <x-field label="Nomor urut" name="ubah-nomor" type="number" x-model="edit.number" />
                <div class="space-y-1.5">
                    <label for="ubah-warna" class="block text-sm font-semibold text-ink">Warna</label>
                    <input id="ubah-warna" type="color" x-model="edit.color" class="h-11 w-full rounded-2xl border border-slate-300 bg-white p-1 focus-visible:outline-2 focus-visible:outline-accent">
                </div>
            </div>
            <x-slot:footer>
                <button type="button" @click="ubah = false" class="min-h-11 rounded-2xl border border-slate-300 px-4 font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent">Batal</button>
                <button  type="button" @click="$wire.update(edit); ubah = false"
                        class="min-h-11 rounded-2xl bg-accent px-4 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Simpan
                </button>
            </x-slot:footer>
        </x-modal>
    </ul>
</section>
