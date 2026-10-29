<?php

use App\Models\Project;
use App\Services\DptService;
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

    public function updatedFile(DptService $service)
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

    public function import(DptService $service)
    {
        if (! $this->sheet) {
            return;
        }

        $this->importedCount = $service->import($this->project, $this->sheet['rows'], $this->mapping);

        $this->reset(['file', 'mapping']);
        $this->dispatch('dpt-changed');
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
            return app(DptService::class)->readSpreadsheet($this->file->getRealPath());
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

        return app(DptService::class)->analyzeRows($this->project, $this->sheet['rows'], $this->mapping);
    }
};
?>

@php
    $selectCls = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $fields = app(\App\Services\DptService::class)->importFields($project);
@endphp

<div class="space-y-8">
    @error('project')
        <p class="rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
    @enderror
    @if ($importedCount !== null)
        <p class="rounded-2xl border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900" role="status">{{ number_format($importedCount, 0, ',', '.') }} baris berhasil diimpor.</p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-slate-600">Langkah 1 — unggah berkas Excel (.xlsx).</p>
        <a href="{{ route('dpt.template', ['project' => $project->code_project]) }}" class="{{ $btnGhost }} inline-flex items-center" :class="terkunci ? 'pointer-events-none opacity-50' : ''">Unduh template</a>
    </div>
    <label class="relative flex min-h-40 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent" :class="terkunci ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:border-accent'">
        <span class="font-bold">Seret berkas ke sini atau klik untuk memilih</span>
        <span class="mt-1 text-sm text-slate-600">Format .xlsx, maksimal 5 MB</span>
        <input type="file" wire:model="file" accept=".xlsx" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" :disabled="terkunci" aria-label="Berkas Excel DPT">
        <span wire:loading wire:target="file" class="mt-3 text-sm text-slate-600">Mengunggah dan membaca berkas...</span>
        @if ($file && $this->sheet)
            <span class="mt-3 text-sm font-semibold text-accent">{{ $file->getClientOriginalName() }} — {{ number_format(count($this->sheet['rows']), 0, ',', '.') }} baris data</span>
        @endif
    </label>
    @error('file') <p class="-mt-4 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror

    @if ($this->sheet)
        <div>
            <p class="mb-3 font-bold">Langkah 2 — pemetaan kolom</p>
            @if ($project->usesWilayah())
                <p class="mb-4 rounded-2xl border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900" role="note">
                    Survei ini memakai wilayah (<strong>{{ $project->wilayah_label }}</strong>). Pastikan kolom {{ $project->wilayah_label }} di berkas Anda dipetakan ke "{{ $project->wilayah_label }}", karena wajib diisi dan dipakai untuk undian, filter, serta ekspor tautan per {{ $project->wilayah_label }}.
                </p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->sheet['headers'] as $i => $header)
                    <div class="space-y-1.5" wire:key="peta-{{ $i }}">
                        <label for="peta-{{ $i }}" class="block text-sm font-semibold">Kolom "{{ $header }}"</label>
                        <select id="peta-{{ $i }}" wire:model.live="mapping.{{ $i }}" :disabled="terkunci" class="{{ $selectCls }}">
                            <option value="">Abaikan kolom ini</option>
                            @foreach ($fields as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            @error('mapping') <p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            @if ($this->analysis['missingFields'])
                <p class="mt-3 text-sm text-amber-900" role="alert">Kolom wajib belum dipetakan: {{ implode(', ', $this->analysis['missingFields']) }}.</p>
            @endif
        </div>

        <div>
            <p class="mb-3 font-bold">Langkah 3 — pratinjau beberapa baris</p>
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full min-w-[32rem] text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Baris</th>
                            @foreach ($fields as $label)<th scope="col" class="px-4 py-3 font-bold">{{ $label }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->analysis['preview'] as $record)
                            <tr wire:key="pratinjau-{{ $record['row'] }}">
                                <td class="px-4 py-3">{{ $record['row'] }}</td>
                                @foreach (array_keys($fields) as $key)<td class="px-4 py-3">{{ $record[$key] ?? '—' }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <p class="mb-3 font-bold">Laporan baris bermasalah ({{ count($this->analysis['problems']) }})</p>
            @if (count($this->analysis['problems']))
                <ul class="max-h-80 space-y-2 overflow-y-auto" tabindex="0">
                    @foreach (array_slice($this->analysis['problems'], 0, 50) as $problem)
                        <li wire:key="masalah-{{ $problem['row'] }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 p-3 text-sm">
                            <x-badge :tone="$problem['tone']">Baris {{ $problem['row'] }}</x-badge>
                            <span class="font-semibold">{{ $problem['name'] }}</span>
                            <span class="text-slate-600">{{ $problem['reason'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if (count($this->analysis['problems']) > 50)
                    <p class="mt-2 text-sm text-slate-600">Menampilkan 50 dari {{ count($this->analysis['problems']) }} baris bermasalah.</p>
                @endif
            @else
                <x-empty-state title="Tidak ada baris bermasalah" />
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" :disabled="terkunci || {{ count($this->analysis['valid']) ? 'false' : 'true' }}" class="min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">
                Impor {{ number_format(count($this->analysis['valid']), 0, ',', '.') }} baris yang valid
            </button>
            <button type="button" wire:click="cancel" class="{{ $btnGhost }}">Batal</button>
        </div>
    @endif
</div>
