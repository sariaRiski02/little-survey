<?php

use App\Models\Project;
use App\Services\AnswerService;
use App\Services\RespondentService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public Project $project;

    public string $search = '';

    public string $filterWilayah = '';

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

    #[Computed]
    public function rows()
    {
        return app(AnswerService::class)->paginate($this->project, $this->search, $this->filterWilayah);
    }

    #[Computed]
    public function questions()
    {
        return app(AnswerService::class)->questions($this->project);
    }

    #[Computed]
    public function wilayahOptions(): array
    {
        return app(RespondentService::class)->wilayahOptions($this->project);
    }
};
?>

@php
    $service = app(\App\Services\AnswerService::class);
    $rows = $this->rows;
    $questions = $this->questions;
    $usesWilayah = $project->usesWilayah();
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $exportUrl = route('jawaban.export', array_filter(['project' => $project->code_project, 'wilayah' => $filterWilayah]));
@endphp

<section aria-labelledby="h-jawaban" class="rounded-2xl border border-slate-200 bg-white p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="h-jawaban" class="text-lg font-extrabold">Daftar jawaban ({{ number_format($rows->total(), 0, ',', '.') }})</h2>
        <a href="{{ $exportUrl }}" class="{{ $btnGhost }} inline-flex items-center">Unduh Excel{{ $filterWilayah !== '' ? ' ('.$filterWilayah.')' : '' }}</a>
    </div>
    <p class="mt-2 text-sm text-slate-600">Hanya responden yang sudah mengisi. Data bersifat rahasia, jangan dibagikan di luar tim survei.</p>

    <div class="mt-4 grid gap-3 {{ $usesWilayah ? 'sm:grid-cols-3' : '' }}">
        <div class="{{ $usesWilayah ? 'sm:col-span-2' : '' }}">
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
    </div>

    @if ($rows->count())
        <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full text-left text-sm" style="min-width: {{ 28 + $questions->count() * 14 }}rem">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-bold">Kode</th>
                        <th scope="col" class="px-4 py-3 font-bold">Nama</th>
                        @if ($usesWilayah)<th scope="col" class="px-4 py-3 font-bold">{{ $project->wilayah_label }}</th>@endif
                        <th scope="col" class="px-4 py-3 font-bold">Waktu mengisi</th>
                        @foreach ($questions as $question)
                            <th scope="col" class="min-w-56 px-4 py-3 font-bold" title="{{ $question->text }}">{{ $question->position }}. {{ \Illuminate\Support\Str::limit($question->text, 60) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr wire:key="jawaban-{{ $row->id }}" class="align-top">
                            <td class="px-4 py-3 font-mono">{{ $row->code() }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $row->name }}</td>
                            @if ($usesWilayah)<td class="px-4 py-3">{{ $row->wilayah }}</td>@endif
                            <td class="whitespace-nowrap px-4 py-3">{{ $row->filled_at->format('d/m/Y H:i') }}</td>
                            @foreach ($questions as $question)
                                @php $text = $service->answerText($row, $question); @endphp
                                <td class="px-4 py-3 {{ $text === '' ? 'text-slate-400' : '' }}">{{ $text === '' ? '-' : $text }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <nav class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Halaman">
            <p class="text-slate-600">Menampilkan {{ number_format($rows->firstItem(), 0, ',', '.') }}–{{ number_format($rows->lastItem(), 0, ',', '.') }} dari {{ number_format($rows->total(), 0, ',', '.') }}</p>
            <div class="flex gap-2">
                <button type="button" wire:click="previousPage" @disabled($rows->onFirstPage()) class="{{ $btnGhost }} disabled:cursor-not-allowed disabled:opacity-50">Sebelumnya</button>
                <button type="button" wire:click="nextPage" @disabled(! $rows->hasMorePages()) class="{{ $btnGhost }} disabled:cursor-not-allowed disabled:opacity-50">Berikutnya</button>
            </div>
        </nav>
    @else
        <x-empty-state class="mt-5" title="Belum ada jawaban" text="Jawaban muncul di sini setelah responden mengisi survei." />
    @endif
</section>
