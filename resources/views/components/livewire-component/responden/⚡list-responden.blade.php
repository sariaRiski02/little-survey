<?php

use App\Models\Project;
use App\Services\RespondentService;
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

    public string $filterStatus = '';

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterWilayah()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    #[On('respondent-changed')]
    public function refreshList()
    {
        $this->project->refresh();
        unset($this->rows, $this->wilayahOptions);
    }

    public function markSent($id, RespondentService $service)
    {
        $service->markSent($this->project, $id);

        $this->afterChange();
    }

    public function regenerate($id, RespondentService $service)
    {
        $service->regenerateLink($this->project, $id);

        $this->afterChange();
    }

    public function delete($id, RespondentService $service)
    {
        $service->delete($this->project, $id);

        $this->afterChange();
        $this->dispatch('respondent-saved');
    }

    private function afterChange(): void
    {
        $this->refreshList();
        $this->dispatch('respondent-changed');
    }

    #[Computed]
    public function rows()
    {
        return app(RespondentService::class)->paginate($this->project, $this->search, $this->filterWilayah, $this->filterStatus);
    }

    #[Computed]
    public function wilayahOptions(): array
    {
        return app(RespondentService::class)->wilayahOptions($this->project);
    }
};
?>

@php
    $service = app(\App\Services\RespondentService::class);
    $rows = $this->rows;
    $usesWilayah = $project->usesWilayah();
    $isRespondentsOnly = ! $project->isFullDptMode();
    $statusLabels = [
        \App\Models\Respondent::STATUS_NOT_SENT => ['Belum dikirim', 'neutral'],
        \App\Models\Respondent::STATUS_SENT => ['Terkirim', 'warning'],
        \App\Models\Respondent::STATUS_FILLED => ['Sudah mengisi', 'success'],
    ];
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
@endphp

<section aria-labelledby="h-tabel" class="rounded-2xl border border-slate-200 bg-white p-6" @respondent-saved.window="hapus = false">
    <h2 id="h-tabel" class="text-lg font-extrabold">Daftar responden</h2>
    @error('project')
        <p class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-4 grid gap-3 {{ $usesWilayah ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }}">
        <div class="sm:col-span-2">
            <label for="cari" class="sr-only">Cari kode atau nama</label>
            <input id="cari" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama" class="{{ $input }}">
        </div>
        @if ($usesWilayah)
            <div>
                <label for="f-wilayah" class="sr-only">Filter {{ $project->wilayah_label }}</label>
                <select id="f-wilayah" wire:model.live="filterWilayah" class="{{ $input }}">
                    <option value="">Semua {{ $project->wilayah_label }}</option>
                    @foreach ($this->wilayahOptions as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="f-status" class="sr-only">Filter status</label>
            <select id="f-status" wire:model.live="filterStatus" class="{{ $input }}">
                <option value="">Semua status</option>
                @foreach ($statusLabels as $key => $label)<option value="{{ $key }}">{{ $label[0] }}</option>@endforeach
            </select>
        </div>
    </div>

    @if ($rows->count())
        <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        @foreach (array_filter(['Kode', 'Nama', $usesWilayah ? $project->wilayah_label : null, 'Status', 'Waktu mengisi']) as $heading)<th scope="col" class="px-4 py-3 font-bold">{{ $heading }}</th>@endforeach
                        <th scope="col" class="px-4 py-3 font-bold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        @php
                            $status = $row->status();
                            $whatsapp = $service->whatsappUrl($project, $row);
                            $filled = $status === \App\Models\Respondent::STATUS_FILLED;
                        @endphp
                        <tr wire:key="responden-{{ $row->id }}" class="align-top">
                            <td class="px-4 py-3 font-mono">{{ $row->code() }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $row->name }}</td>
                            @if ($usesWilayah)<td class="px-4 py-3">{{ $row->wilayah }}</td>@endif
                            <td class="px-4 py-3"><x-badge :tone="$statusLabels[$status][1]">{{ $statusLabels[$status][0] }}</x-badge></td>
                            <td class="px-4 py-3">{{ $row->filled_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="salinTautan({{ $row->id }}, @js($row->surveyUrl()))" class="{{ $btnGhost }}"><span x-text="salin === {{ $row->id }} ? 'Tersalin ✓' : 'Salin tautan'">Salin tautan</span></button>
                                    @unless ($filled)
                                        <button type="button" wire:click="regenerate({{ $row->id }})" wire:confirm="Buat ulang tautan? Tautan lama tidak akan berlaku lagi." class="{{ $btnGhost }}">Buat ulang tautan</button>
                                    @endunless
                                    @if ($whatsapp)
                                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" wire:click="markSent({{ $row->id }})" class="{{ $btnGhost }} inline-flex items-center">Kirim via WhatsApp</a>
                                    @else
                                        <span aria-disabled="true" title="Tidak ada nomor telepon" class="{{ $btnGhost }} inline-flex cursor-not-allowed items-center opacity-50">Kirim via WhatsApp</span>
                                    @endif
                                    @if ($status === \App\Models\Respondent::STATUS_NOT_SENT)
                                        <button type="button" wire:click="markSent({{ $row->id }})" class="{{ $btnGhost }}">Tandai terkirim</button>
                                    @endif
                                    @unless ($filled)
                                        <button type="button" @click="hapusId = {{ $row->id }}; hapusNama = @js($row->name); hapus = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600">Hapus</button>
                                    @endunless
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
        <x-empty-state class="mt-5" title="Belum ada responden" :text="$isRespondentsOnly ? 'Tambah responden atau impor dari Excel.' : 'Lakukan pengundian di halaman Statistik.'" />
    @endif

    <x-modal show="hapus" title="Hapus responden?">
        Responden <strong x-text="hapusNama"></strong> dan tautannya akan dihapus. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="$wire.delete(hapusId)" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>
</section>
