<?php

use App\Models\Project;
use App\Services\RespondentService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Project $project;

    public $file = null;

    /** @var array<int, string> Excel column index => import field name */
    public array $mapping = [];

    public ?int $importedCount = null;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function updatedFile(RespondentService $service)
    {
        $this->importedCount = null;
        $this->mapping = [];

        try {
            $this->validate(
                ['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']],
                ['file.mimes' => 'Berkas harus berformat .xlsx.', 'file.max' => 'Ukuran berkas maksimal 5 MB.'],
            );

            $sheet = $service->readSpreadsheet($this->file->getRealPath());
        } catch (ValidationException $exception) {
            $this->reset('file');

            throw $exception;
        }

        $this->mapping = $service->guessMapping($this->project, $sheet['headers']);
    }

    public function import(RespondentService $service)
    {
        if (! $this->sheet) {
            return;
        }

        $this->importedCount = $service->import($this->project, $this->sheet['rows'], $this->mapping);

        $this->reset(['file', 'mapping']);
        $this->dispatch('respondent-changed');
    }

    public function cancel()
    {
        $this->reset(['file', 'mapping', 'importedCount']);
        $this->resetValidation();
    }

    /**
     * @return array{headers: list<string>, rows: list<array{number: int, values: list<mixed>}>}|null
     */
    #[Computed]
    public function sheet(): ?array
    {
        if (! $this->file) {
            return null;
        }

        try {
            return app(RespondentService::class)->readSpreadsheet($this->file->getRealPath());
        } catch (ValidationException $exception) {
            $this->addError('file', $exception->validator->errors()->first());
            $this->file = null;

            return null;
        }
    }

    #[Computed]
    public function analysis(): ?array
    {
        if (! $this->sheet) {
            return null;
        }

        return app(RespondentService::class)->analyzeRows($this->project, $this->sheet['rows'], $this->mapping);
    }
};
?>

@php
    $fields = app(\App\Services\RespondentService::class)->importFields($project);
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
@endphp

<div>
    <x-modal show="impor" title="Impor responden dari Excel">
        <div class="max-h-[65vh] space-y-6 overflow-y-auto p-1">
            @error('project')
                <p class="rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
            @enderror
            @if ($importedCount !== null)
                <p class="rounded-2xl border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900" role="status">{{ number_format($importedCount, 0, ',', '.') }} baris berhasil diimpor.</p>
            @endif
            <p class="text-sm">Kolom wajib: Nama, NIK, Tanggal lahir, Jenis kelamin{{ $project->usesWilayah() ? ', '.$project->wilayah_label : '' }}. Opsional: Sosial media, Nomor telepon.</p>
            <a href="{{ route('responden.template', ['project' => $project->code_project]) }}" class="{{ $btnGhost }} inline-flex items-center">Unduh template</a>
            <label class="relative flex min-h-32 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center hover:border-accent focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent">
                <span class="font-bold text-ink">Seret berkas atau klik untuk memilih</span>
                <span class="text-xs">Format .xlsx, maksimal 5 MB</span>
                <input type="file" wire:model="file" accept=".xlsx" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" aria-label="Berkas Excel responden">
                <span wire:loading wire:target="file" class="mt-2 text-xs">Mengunggah dan membaca berkas...</span>
                @if ($file && $this->sheet)
                    <span class="mt-2 text-sm font-semibold text-accent">{{ $file->getClientOriginalName() }} — {{ number_format(count($this->sheet['rows']), 0, ',', '.') }} baris data</span>
                @endif
            </label>
            @error('file') <p class="-mt-4 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror

            @if ($this->sheet)
                <div>
                    <p class="mb-2 font-bold text-ink">Pemetaan kolom</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($this->sheet['headers'] as $i => $header)
                            <div class="space-y-1" wire:key="peta-{{ $i }}">
                                <label for="pt-{{ $i }}" class="block text-sm font-semibold text-ink">Kolom "{{ $header }}"</label>
                                <select id="pt-{{ $i }}" wire:model.live="mapping.{{ $i }}" class="{{ $input }}">
                                    <option value="">Abaikan kolom ini</option>
                                    @foreach ($fields as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    @error('mapping') <p class="mt-2 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror
                    @if ($this->analysis['missingFields'])
                        <p class="mt-2 text-sm text-amber-900" role="alert">Kolom wajib belum dipetakan: {{ implode(', ', $this->analysis['missingFields']) }}.</p>
                    @endif
                </div>
                <div>
                    <p class="mb-2 font-bold text-ink">Pratinjau</p>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="w-full min-w-[30rem] text-left text-sm">
                            <thead class="bg-slate-50"><tr><th scope="col" class="px-3 py-2 font-bold">Baris</th>@foreach ($fields as $label)<th scope="col" class="px-3 py-2 font-bold">{{ $label }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($this->analysis['preview'] as $record)
                                    <tr wire:key="pratinjau-{{ $record['row'] }}"><td class="px-3 py-2">{{ $record['row'] }}</td>@foreach (array_keys($fields) as $key)<td class="px-3 py-2">{{ $record[$key] ?? '—' }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <p class="mb-2 font-bold text-ink">Laporan baris bermasalah ({{ count($this->analysis['problems']) }})</p>
                    <ul class="max-h-60 space-y-2 overflow-y-auto" tabindex="0">
                        @foreach (array_slice($this->analysis['problems'], 0, 50) as $problem)
                            <li wire:key="masalah-{{ $problem['row'] }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 p-3 text-sm">
                                <x-badge :tone="$problem['tone']">Baris {{ $problem['row'] }}</x-badge>
                                <span class="font-semibold text-ink">{{ $problem['name'] }}</span><span>{{ $problem['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if (count($this->analysis['problems']) > 50)
                        <p class="mt-2 text-xs">Menampilkan 50 dari {{ count($this->analysis['problems']) }} baris bermasalah.</p>
                    @endif
                </div>
            @endif
        </div>
        <x-slot:footer>
            <button type="button" @click="impor = false" wire:click="cancel" class="{{ $btnGhost }}">Tutup</button>
            @if ($this->sheet)
                <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" :disabled="{{ count($this->analysis['valid']) ? 'false' : 'true' }}" class="min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">Impor {{ number_format(count($this->analysis['valid']), 0, ',', '.') }} baris valid</button>
            @endif
        </x-slot:footer>
    </x-modal>
</div>
