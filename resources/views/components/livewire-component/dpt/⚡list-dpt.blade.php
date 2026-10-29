<?php

use App\Models\Project;
use App\Services\DptService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Project $project;

    public string $search = '';

    public string $filterWilayah = '';

    /** @var list<string> */
    public array $selected = [];

    public bool $selectAllMatching = false;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedFilterWilayah()
    {
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedSelected()
    {
        $this->selectAllMatching = false;
    }

    public function togglePage()
    {
        $pageIds = $this->rows->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = $this->pageIsSelected()
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique([...$this->selected, ...$pageIds]));
        $this->selectAllMatching = false;
    }

    public function selectAllFiltered()
    {
        $this->selectAllMatching = true;
    }

    public function clearSelection()
    {
        $this->selected = [];
        $this->selectAllMatching = false;
    }

    public function pageIsSelected(): bool
    {
        $pageIds = $this->rows->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();

        return $pageIds !== [] && array_diff($pageIds, $this->selected) === [];
    }

    public function deleteSelected(DptService $service)
    {
        if ($this->selectAllMatching) {
            $service->deleteMatching($this->project, $this->search, $this->filterWilayah);
        } else {
            $service->deleteMany($this->project, $this->selected);
        }

        $this->clearSelection();
        $this->resetPage();
        $this->refreshList();
        $this->dispatch('dpt-changed');
        $this->dispatch('dpt-saved');
    }

    #[On('dpt-changed')]
    public function refreshList()
    {
        unset($this->rows, $this->wilayahOptions, $this->selectedCount);
    }

    public function delete($id, DptService $service)
    {
        $service->delete($this->project, $id);

        $this->refreshList();
        $this->dispatch('dpt-changed');
        $this->dispatch('dpt-saved');
    }

    public function update(array $dataDpt, DptService $service)
    {
        $id = $dataDpt['id'] ?? null;
        $dpt = $service->find($this->project, $id);

        $data = [
            'name' => trim((string) ($dataDpt['name'] ?? '')),
            'birth_date' => (string) ($dataDpt['birth_date'] ?? ''),
        ];

        if ($this->project->usesWilayah()) {
            $data['wilayah'] = trim((string) ($dataDpt['wilayah'] ?? ''));
        }

        $service->validator($this->project, $data)->validate();
        $service->update($this->project, $id, $data);

        $this->refreshList();
        $this->dispatch('dpt-changed');
        $this->dispatch('dpt-saved');
    }

    public function clean()
    {
        $this->resetValidation();
    }

    #[Computed]
    public function rows()
    {
        return app(DptService::class)->paginate($this->project, $this->search, $this->filterWilayah);
    }

    #[Computed]
    public function selectedCount(): int
    {
        return $this->selectAllMatching ? $this->rows->total() : count($this->selected);
    }

    #[Computed]
    public function wilayahOptions(): array
    {
        return app(DptService::class)->wilayahOptions($this->project);
    }
};
?>

@php
    $selectCls = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $usesWilayah = $project->usesWilayah();
    $rows = $this->rows;
@endphp

<section aria-labelledby="h-tabel" class="rounded-2xl border border-slate-200 bg-white p-6" @dpt-saved.window="ubah = false; hapus = false; hapusMassal = false">
    <h2 id="h-tabel" class="text-lg font-extrabold">Data DPT</h2>
    @error('project')
        <p class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-4 grid gap-3 {{ $usesWilayah ? 'sm:grid-cols-3' : '' }}">
        <div class="{{ $usesWilayah ? 'sm:col-span-2' : '' }}">
            <label for="cari" class="sr-only">Cari nama atau NIK</label>
            <input id="cari" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau NIK" class="{{ $selectCls }}">
        </div>
        @if ($usesWilayah)
            <div>
                <label for="filter-wilayah" class="sr-only">Filter {{ $project->wilayah_label }}</label>
                <select id="filter-wilayah" wire:model.live="filterWilayah" class="{{ $selectCls }}">
                    <option value="">Semua {{ $project->wilayah_label }}</option>
                    @foreach ($this->wilayahOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </div>
        @endif
    </div>

    @if ($rows->count())
        @if ($this->selectedCount > 0)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm" role="status">
                <p>
                    <strong>{{ number_format($this->selectedCount, 0, ',', '.') }}</strong> data dipilih.
                    @if (! $selectAllMatching && $rows->total() > count($selected) && $this->pageIsSelected())
                        <button type="button" wire:click="selectAllFiltered" class="font-bold text-accent underline focus-visible:outline-2 focus-visible:outline-accent">Pilih semua {{ number_format($rows->total(), 0, ',', '.') }} data{{ $search !== '' || $filterWilayah !== '' ? ' hasil filter' : '' }}</button>
                    @endif
                </p>
                <div class="flex gap-2">
                    <button type="button" wire:click="clearSelection" class="{{ $btnGhost }}">Batal pilih</button>
                    <button type="button" :disabled="terkunci" @click="hapusMassal = true" class="min-h-11 rounded-2xl bg-rose-700 px-4 text-sm font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 disabled:cursor-not-allowed disabled:opacity-50">Hapus terpilih</button>
                </div>
            </div>
        @endif
        <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="w-12 px-4 py-3">
                            <input type="checkbox" wire:click="togglePage" @checked($this->pageIsSelected()) :disabled="terkunci" aria-label="Pilih semua data di halaman ini" class="h-5 w-5 rounded border-slate-300 accent-teal-700">
                        </th>
                        @foreach (array_filter(['Nama', 'NIK', 'Tanggal lahir', $usesWilayah ? $project->wilayah_label : null, 'Responden']) as $heading)<th scope="col" class="px-4 py-3 font-bold">{{ $heading }}</th>@endforeach
                        <th scope="col" class="px-4 py-3 text-right font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr wire:key="dpt-{{ $row->id }}">
                            <td class="px-4 py-3">
                                <input type="checkbox" wire:model.live="selected" value="{{ $row->id }}" :disabled="terkunci" aria-label="Pilih {{ $row->name }}" class="h-5 w-5 rounded border-slate-300 accent-teal-700">
                            </td>
                            <td class="px-4 py-3 font-semibold">{{ $row->name }}</td>
                            <td class="px-4 py-3 font-mono">{{ $row->nik }}</td>
                            <td class="px-4 py-3">{{ $row->birth_date->format('d/m/Y') }}</td>
                            @if ($usesWilayah)<td class="px-4 py-3">{{ $row->wilayah }}</td>@endif
                            <td class="px-4 py-3"><x-badge :tone="$row->is_selected ? 'success' : 'neutral'">{{ $row->is_selected ? 'Terpilih' : 'Tidak terpilih' }}</x-badge></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" :disabled="terkunci" wire:click="clean" @click="edit = { id: @js($row->id), name: @js($row->name), birth_date: @js($row->birth_date->format('Y-m-d')), wilayah: @js($row->wilayah) }; ubah = true" class="{{ $btnGhost }}">Ubah</button>
                                    <button type="button" :disabled="terkunci" @click="hapusNama = @js($row->name); hapusId = @js($row->id); hapus = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <nav class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Halaman">
            <p class="text-slate-600">Menampilkan {{ number_format($rows->firstItem(), 0, ',', '.') }}–{{ number_format($rows->lastItem(), 0, ',', '.') }} dari {{ number_format($rows->total(), 0, ',', '.') }}</p>
            <div class="flex gap-2">
                <button type="button" wire:click="previousPage" @disabled($rows->onFirstPage()) class="{{ $btnGhost }}">Sebelumnya</button>
                <button type="button" wire:click="nextPage" @disabled(! $rows->hasMorePages()) class="{{ $btnGhost }}">Berikutnya</button>
            </div>
        </nav>
    @else
        <x-empty-state class="mt-5" title="Belum ada data DPT" text="Impor berkas Excel atau tambah data secara manual." />
    @endif

    <x-modal show="hapus" title="Hapus data DPT?">
        Data <strong x-text="hapusNama"></strong> akan dihapus dari DPT. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="$wire.delete(hapusId)" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="hapusMassal" title="Hapus data terpilih?">
        <strong>{{ number_format($this->selectedCount, 0, ',', '.') }}</strong> data DPT akan dihapus. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapusMassal = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" wire:click="deleteSelected" wire:loading.attr="disabled" wire:target="deleteSelected" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 disabled:opacity-50">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="ubah" title="Ubah data DPT">
        <div class="space-y-4">
            <div>
                <x-field label="Nama" name="ubah-nama" x-model="edit.name" />
                @error('name') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-field label="Tanggal lahir" name="ubah-lahir" type="date" x-model="edit.birth_date" />
                @error('birth_date') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
            </div>
            @if ($usesWilayah)
                <div>
                    <x-field :label="$project->wilayah_label" name="ubah-wilayah" x-model="edit.wilayah" />
                    @error('wilayah') <p class="mt-1 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
        <x-slot:footer>
            <button type="button" @click="ubah = false" wire:click="clean" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="$wire.update(edit)" wire:loading.attr="disabled" wire:target="update" class="{{ $btnPrimary }}">Simpan</button>
        </x-slot:footer>
    </x-modal>
</section>
