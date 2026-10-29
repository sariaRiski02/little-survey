<?php

use App\Models\Candidate;
use App\Models\Project;
use App\Services\CandidateService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


new class extends Component
{
    use WithFileUploads;

    public Project $project;
    public $photo = null;
    public function mount(Project $project){
        $this->project = $project;
    }

    #[On('candidate-added')]
    public function refreshList(){
        $this->project->refresh();
    }


    public function clean(){
        $this->photo = null;
        $this->resetValidation();
    }

    public function delete($id, CandidateService $service)
    {
        $service->delete($this->project, $id);
        $this->project->refresh();
        $this->dispatch('candidate-saved');
    }

    public function update(array $dataCandidate, CandidateService $service)
    {
        $data = [
            'name' => trim((string) ($dataCandidate['name'] ?? '')),
            'number' => ($dataCandidate['number'] ?? '') === '' ? null : $dataCandidate['number'],
            'color' => $dataCandidate['color'] ?? null,
            'photo' => $this->photo,
        ];
        $id = $dataCandidate['id'] ?? null;

        Validator::make($data, [
            'name' => 'required|string|max:255',
            'number' => ['nullable', 'integer', 'min:1', Rule::unique('candidates', 'number')
                ->where('project_id', $this->project->id)
                ->ignore($id)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'photo' => 'nullable|image|max:1024',
        ])->validate();

        $service->update($this->project, [
            'name' => strtolower($data['name']),
            'number' => $data['number'],
            'color' => $data['color'],
            'picture' => $this->photo,
        ], $id);

        $this->clean();
        $this->project->refresh();
        $this->dispatch('candidate-saved');
    }

};
?>
<section aria-labelledby="h-daftar" @candidate-saved.window="ubah = false; hapus = false">
    <h2 id="h-daftar" class="mb-4 text-lg font-extrabold">Daftar kandidat ({{ $project->candidate->count() }})</h2>
    @error('project')
        <p class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">{{ $message }}</p>
    @enderror
    <ul class="grid gap-4 sm:grid-cols-2">
        @foreach($project->candidate as $candidate)
        <li class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-center gap-4">
                @if ($candidate->picture)
                    <img src="{{ asset('storage/'.$candidate->picture) }}" alt="{{ $candidate->name }}" class="h-14 w-14 shrink-0 rounded-2xl object-cover">

                @else
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-lg font-extrabold text-white" style="background-color: {{ $candidate->color }}" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($candidate->name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') }}</span>
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
                                name: @js($candidate->name),
                                number: @js($candidate->number),
                                color: @js($candidate->color),
                                picture: @js($candidate->picture),
                                id: @js($candidate->id)
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
            <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                <div class="space-y-1.5">
                    <p class="text-sm font-semibold text-ink">Foto profil</p>
                    {{-- Show old picture --}}
                    <img x-show="edit.picture"
                        x-bind:src="'{{ asset('storage') }}/' + edit.picture"
                        alt="Pratinjau foto profil kandidat"
                        class="h-32 w-32 rounded-2xl object-cover">
                    <p x-show="!edit.picture"
                        class="text-sm text-slate-600">
                        Belum ada foto profil.
                    </p>
                    <label for="ubah-foto" class="block text-sm font-semibold text-ink">
                            Pilih foto pengganti
                    </label>
                    <input id="ubah-foto" type="file" wire:model="photo" accept="image/*" class="block w-full text-sm text-slate-600 file:mr-4 file:min-h-11 file:rounded-2xl file:border-0 file:bg-slate-100 file:px-4 file:font-bold hover:file:bg-slate-200">
                    <p wire:loading wire:target="photo" class="text-sm text-slate-600">Mengunggah foto...</p>
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto pengganti" class="h-32 w-32 rounded-2xl object-cover">
                    @endif
                    @error('photo') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <x-field label="Nama" name="ubah-nama" x-model="edit.name" />
                @error('name') <p class="-mt-3 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                <x-field label="Nomor urut" name="ubah-nomor" type="number" x-model="edit.number" />
                @error('number') <p class="-mt-3 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                <div class="space-y-1.5">
                    <label for="ubah-warna" class="block text-sm font-semibold text-ink">Warna</label>
                    <input id="ubah-warna" type="color" x-model="edit.color" class="h-11 w-full rounded-2xl border border-slate-300 bg-white p-1 focus-visible:outline-2 focus-visible:outline-accent">
                    @error('color') <p class="text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <x-slot:footer>
                <button type="button" @click="ubah = false" class="min-h-11 rounded-2xl border border-slate-300 px-4 font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent" wire:click="clean" >Batal</button>
                <button  type="button" @click="$wire.update(edit)" wire:loading.attr="disabled" wire:target="update"
                        class="min-h-11 rounded-2xl bg-accent px-4 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Simpan
                </button>
            </x-slot:footer>
        </x-modal>
    </ul>
</section>
