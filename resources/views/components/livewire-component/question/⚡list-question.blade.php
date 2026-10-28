<?php

use App\Models\Project;
use App\Models\Question;
use App\Services\QuestionService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    #[On('question-changed')]
    public function refreshList()
    {
        $this->project->refresh();
    }

    public function move($id, string $direction, QuestionService $service)
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 422);

        $service->move($this->project, $id, $direction);
        $this->dispatch('question-changed');
    }

    public function delete($id, QuestionService $service)
    {
        $service->delete($this->project, $id);

        $this->dispatch('question-changed');
        $this->dispatch('question-saved');
    }

    public function applyTemplate(QuestionService $service)
    {
        $service->applyTemplate($this->project);

        $this->dispatch('question-changed');
        $this->dispatch('question-saved');
    }

    public function with(QuestionService $service): array
    {
        $questions = $service->list($this->project);

        return [
            'questions' => $questions,
            'minutes' => $service->estimatedMinutes($questions->count()),
            'choices' => $questions->mapWithKeys(fn (Question $question) => [$question->id => $service->choices($this->project, $question)]),
        ];
    }
};
?>

@php
    $btnGhost = 'min-h-11 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $positions = $questions->pluck('position', 'id');
@endphp

<section aria-labelledby="h-daftar" @question-saved.window="hapus = false; template = false">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="h-daftar" class="text-lg font-extrabold">Daftar pertanyaan ({{ $questions->count() }})</h2>
        <p class="text-sm text-slate-600">Perkiraan durasi pengisian: <strong>± {{ $minutes }} menit</strong></p>
    </div>
    @error('project')
        <p class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">{{ $message }}</p>
    @enderror

    @if ($questions->isNotEmpty())
        <ol class="space-y-3">
            @foreach ($questions as $question)
                <li wire:key="pertanyaan-{{ $question->id }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-extrabold" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">{{ $question->text }}</p>
                            @if ($question->description)<p class="mt-1 text-sm text-slate-600">{{ $question->description }}</p>@endif
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold">
                                <span class="rounded-full bg-slate-100 px-3 py-1">{{ \App\Models\Question::TYPES[$question->type] ?? $question->type }}</span>
                                <span class="rounded-full px-3 py-1 {{ $question->is_required ? 'bg-teal-50 text-teal-800' : 'bg-slate-100 text-slate-700' }}">{{ $question->is_required ? 'Wajib' : 'Opsional' }}</span>
                                @if ($question->has_refuse_option)<span class="rounded-full bg-slate-100 px-3 py-1">Ada “Tidak mau menjawab”</span>@endif
                                @if ($question->branch_question_id && isset($positions[$question->branch_question_id]))
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">Tampil jika no. {{ $positions[$question->branch_question_id] }} = {{ $question->branch_answer }}</span>
                                @endif
                            </div>
                            @if ($question->type === \App\Models\Question::TYPE_CANDIDATE)
                                <p class="mt-2 text-xs text-slate-600">Pilihan diambil dari data kandidat dan diacak per responden.</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <button type="button" :disabled="terkunci || {{ $loop->first ? 'true' : 'false' }}" wire:click="move({{ $question->id }}, 'up')" aria-label="Naikkan pertanyaan {{ $loop->iteration }}" class="{{ $btnGhost }}">↑ Naik</button>
                        <button type="button" :disabled="terkunci || {{ $loop->last ? 'true' : 'false' }}" wire:click="move({{ $question->id }}, 'down')" aria-label="Turunkan pertanyaan {{ $loop->iteration }}" class="{{ $btnGhost }}">↓ Turun</button>
                        <button type="button" :disabled="terkunci" @click="hapusId = {{ $question->id }}; hapusText = @js($question->text); hapus = true" class="min-h-11 rounded-2xl border border-rose-300 px-4 text-sm font-bold text-rose-700 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50">Hapus</button>
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <x-empty-state title="Belum ada pertanyaan" text="Tambahkan pertanyaan atau mulai dari template." />
    @endif

    <x-modal show="hapus" title="Hapus pertanyaan?">
        Pertanyaan “<span x-text="hapusText"></span>” akan dihapus. Tindakan ini tidak dapat dibatalkan.
        <x-slot:footer>
            <button type="button" @click="hapus = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="$wire.delete(hapusId)" class="min-h-11 rounded-2xl bg-rose-700 px-4 font-bold text-white hover:bg-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">Ya, hapus</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="template" title="Buat dari template">
        <p>Paket <strong>Survei Preferensi Pemilih</strong> berisi pertanyaan berikut:</p>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm">
            @foreach (\App\Services\QuestionService::TEMPLATE as $item)<li>{{ $item['text'] }}</li>@endforeach
        </ol>
        <p class="mt-3 text-xs">Pertanyaan ditambahkan setelah pertanyaan yang sudah ada.</p>
        <x-slot:footer>
            <button type="button" @click="template = false" class="{{ $btnGhost }}">Batal</button>
            <button type="button" @click="$wire.applyTemplate()" class="{{ $btnPrimary }}">Gunakan template</button>
        </x-slot:footer>
    </x-modal>

    <x-modal show="preview" title="Pratinjau tampilan responden">
        <div class="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
            @forelse ($questions as $question)
                <div wire:key="pratinjau-{{ $question->id }}">
                    <p class="font-bold text-ink">{{ $loop->iteration }}. {{ $question->text }}@if ($question->is_required)<span class="text-rose-700"> *</span>@endif</p>
                    @if ($question->description)<p class="text-xs text-slate-500">{{ $question->description }}</p>@endif
                    <div class="mt-2 space-y-1 text-sm">
                        @if ($question->type === \App\Models\Question::TYPE_TEXT)
                            <div class="min-h-11 rounded-2xl border border-slate-300 px-4 py-3 text-slate-500">Jawaban Anda…</div>
                        @else
                            @foreach ($choices[$question->id] as $choice)
                                <label class="flex min-h-11 items-center gap-3 rounded-2xl border border-slate-200 px-4"><input type="{{ $question->type === \App\Models\Question::TYPE_MULTIPLE ? 'checkbox' : 'radio' }}" disabled class="h-4 w-4"><span>{{ $choice }}</span></label>
                            @endforeach
                            @if ($question->has_refuse_option)
                                <label class="flex min-h-11 items-center gap-3 rounded-2xl border border-slate-200 px-4"><input type="radio" disabled class="h-4 w-4">Tidak mau menjawab</label>
                            @endif
                        @endif
                    </div>
                    @if ($question->type === \App\Models\Question::TYPE_CANDIDATE)<p class="mt-1 text-xs text-slate-500">Urutan kandidat diacak per responden.</p>@endif
                </div>
            @empty
                <p class="text-sm text-slate-600">Belum ada pertanyaan.</p>
            @endforelse
        </div>
        <x-slot:footer>
            <button type="button" @click="preview = false" class="{{ $btnPrimary }}">Tutup</button>
        </x-slot:footer>
    </x-modal>
</section>
