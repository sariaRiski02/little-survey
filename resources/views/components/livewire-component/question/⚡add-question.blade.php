<?php

use App\Models\Project;
use App\Models\Question;
use App\Services\QuestionService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public string $text = '';

    public string $description = '';

    public string $type = Question::TYPE_SINGLE;

    public string $options = '';

    public bool $is_required = true;

    public bool $has_refuse_option = false;

    public string $branch_question_id = '';

    public string $branch_answer = '';

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    #[On('question-changed')]
    public function refreshForm()
    {
        $exists = $this->branch_question_id !== ''
            && $this->project->Question()->whereKey($this->branch_question_id)->exists();

        if (! $exists) {
            $this->reset(['branch_question_id', 'branch_answer']);
        }
    }

    public function updatedBranchQuestionId()
    {
        $this->branch_answer = '';
    }

    public function save(QuestionService $service)
    {
        $service->create($this->project, [
            'text' => $this->text,
            'description' => $this->description,
            'type' => $this->type,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'has_refuse_option' => $this->has_refuse_option,
            'branch_question_id' => $this->branch_question_id === '' ? null : $this->branch_question_id,
            'branch_answer' => $this->branch_answer === '' ? null : $this->branch_answer,
        ]);

        $this->reset(['text', 'description', 'options', 'branch_question_id', 'branch_answer', 'has_refuse_option']);
        $this->is_required = true;
        $this->resetValidation();
        $this->dispatch('question-changed');
    }

    public function with(QuestionService $service): array
    {
        $questions = $service->list($this->project);

        return [
            'questions' => $questions,
            'branchParents' => $questions->filter(fn (Question $question) => $question->hasOptions()),
            'candidateNames' => $this->type === Question::TYPE_CANDIDATE
                ? $this->project->Candidate()->orderBy('number')->orderBy('name')->pluck('name')->map(fn ($name) => ucwords($name))
                : collect(),
        ];
    }
};
?>

@php
    $btnPrimary = 'min-h-11 rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50';
    $input = 'min-h-11 w-full rounded-2xl border border-slate-300 bg-white px-4 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $area = 'w-full rounded-2xl border border-slate-300 bg-white p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $branchParent = $branchParents->firstWhere('id', (int) $branch_question_id);
@endphp

<section aria-labelledby="h-tambah" class="h-fit rounded-2xl border border-slate-200 bg-white p-6">
    <h2 id="h-tambah" class="text-lg font-extrabold">Tambah pertanyaan</h2>
    <form wire:submit="save" class="mt-5">
        @error('project')
            <p class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900" role="alert">{{ $message }}</p>
        @enderror
        <fieldset :disabled="terkunci" class="space-y-5 disabled:opacity-50">
            <div class="space-y-1.5">
                <label for="p-teks" class="block text-sm font-semibold">Teks pertanyaan</label>
                <textarea id="p-teks" rows="3" wire:model="text" required class="{{ $area }}"></textarea>
                @error('text') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-field label="Keterangan" name="p-ket" wire:model="description" help="Opsional. Petunjuk singkat di bawah pertanyaan." />
                @error('description') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1.5">
                <label for="p-tipe" class="block text-sm font-semibold">Tipe</label>
                <select id="p-tipe" wire:model.live="type" class="{{ $input }}">
                    @foreach (\App\Models\Question::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
                @error('type') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>
            @if (in_array($type, \App\Models\Question::OPTION_TYPES, true))
                <div class="space-y-1.5">
                    <label for="p-pilihan" class="block text-sm font-semibold">Pilihan jawaban</label>
                    <textarea id="p-pilihan" rows="4" wire:model="options" aria-describedby="p-pilihan-help" class="{{ $area }}"></textarea>
                    <p id="p-pilihan-help" class="text-xs text-slate-600">Satu pilihan per baris. Untuk skala, isi label tiap tingkat.</p>
                    @error('options') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif
            @if ($type === \App\Models\Question::TYPE_CANDIDATE)
                <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-bold">Pilihan diambil dari data kandidat dan diacak per responden:</p>
                    <ul class="mt-2 list-disc pl-5">@foreach ($candidateNames as $name)<li>{{ $name }}</li>@endforeach</ul>
                    <p class="mt-2 text-xs text-slate-600">Kelola kandidat di menu Kandidat.</p>
                </div>
            @endif
            <div class="space-y-1">
                <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="is_required" class="h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent"> Wajib dijawab</label>
                @if ($type !== \App\Models\Question::TYPE_TEXT)
                    <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" wire:model="has_refuse_option" class="h-5 w-5 rounded accent-accent focus-visible:outline-2 focus-visible:outline-accent"> Sediakan opsi “Tidak mau menjawab”</label>
                @endif
            </div>

            <fieldset class="space-y-3 rounded-2xl border border-slate-200 p-4">
                <legend class="px-2 text-sm font-bold">Percabangan (opsional)</legend>
                <p class="text-xs text-slate-600">Tampilkan hanya jika pertanyaan X dijawab Y.</p>
                <div class="space-y-1.5">
                    <label for="p-cabang-x" class="block text-sm font-semibold">Pertanyaan X</label>
                    <select id="p-cabang-x" wire:model.live="branch_question_id" class="{{ $input }}">
                        <option value="">Selalu tampilkan</option>
                        @foreach ($branchParents as $parent)
                            <option wire:key="cabang-{{ $parent->id }}" value="{{ $parent->id }}">{{ $parent->position }}. {{ \Illuminate\Support\Str::limit($parent->text, 40) }}</option>
                        @endforeach
                    </select>
                    @error('branch_question_id') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </div>
                @if ($branchParent)
                    <div class="space-y-1.5">
                        <label for="p-cabang-y" class="block text-sm font-semibold">Dijawab Y</label>
                        <select id="p-cabang-y" wire:model="branch_answer" class="{{ $input }}">
                            <option value="">Pilih jawaban</option>
                            @foreach ($branchParent->options ?? [] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                        @error('branch_answer') <p class="text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endif
            </fieldset>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="{{ $btnPrimary }} w-full">Tambah pertanyaan</button>
        </fieldset>
        <p x-cloak x-show="terkunci" class="mt-3 text-sm font-semibold text-amber-900">Formulir dinonaktifkan karena pertanyaan terkunci.</p>
    </form>
</section>
