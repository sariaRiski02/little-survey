<?php

use App\Models\Project;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public $search = '';

    public ?int $projectId = null;

    public string $name = '';

    public string $description = '';

    #[Computed]
    public function projects()
    {
        $query = Project::latest();
        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }
        return $query->get();
    }

    public function prepare(?int $id = null): void
    {
        $this->resetValidation();

        $project = $id ? Project::findOrFail($id) : null;

        $this->projectId = $project?->id;
        $this->name = $project?->name ?? '';
        $this->description = $project?->description ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
        ], [
            'name.required' => 'Nama proyek wajib diisi.',
            'name.max' => 'Nama proyek maksimal 255 karakter.',
            'description.required' => 'Deskripsi wajib diisi.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
        ]);

        if ($this->projectId) {
            Project::findOrFail($this->projectId)->update($validated);
        } else {
            Project::create($validated + ['code_project' => $this->generateCodeProject()]);
        }

        $this->prepare();
        unset($this->projects);
        $this->dispatch('project-saved');
    }

    public function delete(): void
    {
        Project::findOrFail($this->projectId)->delete();

        $this->prepare();
        unset($this->projects);
        $this->dispatch('project-deleted');
    }

    protected function generateCodeProject(): string
    {
        do {
            $code = 'PRJ-' . strtoupper(Str::random(6));
        } while (Project::where('code_project', $code)->exists());

        return $code;
    }
};
?>


<div x-data="{
        menu: null, menuPos: { top: 0, left: 0 },
        hapus: false,
        edit: false,
        baru: false,
        bukaMenu(e, id) {
            if (this.menu === id) { this.menu = null; return; }
            const r = e.currentTarget.getBoundingClientRect();
            this.menuPos = { top: r.bottom + 4, left: Math.max(8, r.right - 176) };
            this.menu = id;
        },
        async mintaBaru() { await $wire.prepare(); this.baru = true; },
        async mintaEdit() { const id = this.menu; this.menu = null; await $wire.prepare(id); this.edit = true; },
        async mintaHapus() { const id = this.menu; this.menu = null; await $wire.prepare(id); this.hapus = true; }
     }"
     @project-saved="edit = false; baru = false" @project-deleted="hapus = false"
     @keydown.escape.window="menu = null" @scroll.window="menu = null" @resize.window="menu = null">

    <x-page-header title="Proyek" subtitle="Pilih proyek survei yang ingin Anda kelola, atau buat yang baru." />

    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto]">
        <div>
            <label for="cari" class="sr-only">Cari nama proyek</label>
            <input id="cari" wire:model.live="search" type="search" placeholder="Cari nama proyek" class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:model="search">
        </div>
        <button type="button" @click="mintaBaru()" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Proyek baru</button>
    </div>

    <section aria-labelledby="h-tabel" class="rounded-2xl border border-slate-200 bg-white">
        <h2 id="h-tabel" class="sr-only">Daftar proyek</h2>
        <div class="overflow-x-auto rounded-2xl">
            <table class="w-full min-w-xl text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-bold">Nama proyek</th>
                        <th scope="col" class="px-4 py-3 font-bold">Kode proyek</th>
                        <th scope="col" class="px-4 py-3 font-bold">Dibuat</th>
                        <th scope="col" class="px-4 py-3 text-right font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">

                    @if($this->projects->isNotEmpty())
                        @foreach ($this->projects as $project)
                            <tr wire:key="project-{{ $project->id }}" class="align-middle">
                                <td class="px-4 py-4"><p class="font-bold">{{ $project->name }}</p></td>
                                <td class="whitespace-nowrap px-4 py-4 font-mono text-slate-600">{{ $project->code_project }}</td>
                                <td class="whitespace-nowrap px-4 py-4">{{ $project->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('dashboard', $project->code_project) }}" aria-label="Masuk ke {{ $project->name }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent text-sm">Masuk</a>
                                        <button type="button" @click="bukaMenu($event, {{ $project->id }})" aria-label="Menu lain untuk {{ $project->name }}" aria-haspopup="menu" :aria-expanded="menu === {{ $project->id }}"
                                                class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-300 bg-white hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-slate-500">Tidak ada proyek yang ditemukan.</td>
                        </tr>
                    @endif


                </tbody>
            </table>
        </div>
    </section>

    {{-- Menu kecil (di luar tabel agar tidak terpotong overflow) --}}
    <div x-cloak x-show="menu !== null" @click.outside="menu = null" role="menu" aria-label="Menu proyek"
         class="fixed z-40 w-44 rounded-2xl border border-slate-200 bg-white p-1 shadow-lg" :style="'top:' + menuPos.top + 'px; left:' + menuPos.left + 'px'">
        <button type="button" role="menuitem" @click="mintaEdit()" class="flex min-h-11 w-full items-center rounded-xl px-3 text-left text-sm font-semibold text-ink hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-accent">Edit</button>
        <button type="button" role="menuitem" @click="mintaHapus()" class="flex min-h-11 w-full items-center rounded-xl px-3 text-left text-sm font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-rose-600">Hapus</button>
    </div>

    {{-- Modal edit --}}
    <x-modal show="edit" title="Edit proyek">
        <div class="space-y-4 p-1">
            <div class="space-y-1.5">
                <label for="e-nama" class="block text-sm font-semibold text-ink">Nama proyek</label>
                <input id="e-nama" type="text" wire:model="name" wire:keydown.enter="save" required class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                @error('name') <p class="text-xs font-semibold text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1.5">
                <label for="e-deskripsi" class="block text-sm font-semibold text-ink">Deskripsi</label>
                <textarea id="e-deskripsi" wire:model="description" rows="3" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                @error('description') <p class="text-xs font-semibold text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:footer>
            <button type="button" @click="edit = false" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Batal</button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-60">Simpan</button>
        </x-slot:footer>
    </x-modal>

    {{-- Modal hapus --}}
    <x-modal show="hapus" title="Hapus proyek?">
        <p class="mb-3 text-sm">Proyek <strong class="text-ink">{{ $name }}</strong> akan dihapus.</p>
        <p class="rounded-2xl bg-rose-50 p-3 text-sm font-semibold text-rose-800">Semua data proyek ikut hilang: kandidat, pertanyaan, responden, dan jawaban. Tindakan ini tidak dapat dibatalkan.</p>
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Batal</button>
            <button type="button" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"
                    class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold disabled:opacity-60 text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Hapus proyek</button>
        </x-slot:footer>
    </x-modal>

    {{-- Modal proyek baru --}}
    <x-modal show="baru" title="Proyek baru">
        <div class="space-y-4 p-1">
            <div class="space-y-1.5">
                <label for="b-nama" class="block text-sm font-semibold text-ink">Nama proyek</label>
                <input id="b-nama" type="text" wire:model="name" wire:keydown.enter="save" required class="min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                @error('name') <p class="text-xs font-semibold text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1.5">
                <label for="b-deskripsi" class="block text-sm font-semibold text-ink">Deskripsi</label>
                <textarea id="b-deskripsi" wire:model="description" rows="3" required class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                @error('description') <p class="text-xs font-semibold text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:footer>
            <button type="button" @click="baru = false" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">Batal</button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:opacity-60">Buat proyek</button>
        </x-slot:footer>
    </x-modal>
</div>
