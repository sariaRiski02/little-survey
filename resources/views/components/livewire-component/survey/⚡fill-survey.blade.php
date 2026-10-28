<?php

use App\Models\Question;
use App\Models\Respondent;
use App\Services\SurveyService;
use Livewire\Component;

new class extends Component
{
    public Respondent $respondent;

    /** @var array<int|string, string|list<string>|null> question id => answer */
    public array $answers = [];

    public bool $done = false;

    public function mount(Respondent $respondent)
    {
        $this->respondent = $respondent;
        $this->done = $respondent->filled_at !== null;
    }

    public function submit(SurveyService $service)
    {
        $service->submit($this->respondent, $this->answers);

        $this->done = true;
    }

    public function with(SurveyService $service): array
    {
        $questions = $service->visibleQuestions($service->questions($this->respondent->Project), $this->answers);

        return [
            'questions' => $this->done ? collect() : $questions,
            'choices' => $questions->mapWithKeys(fn (Question $question) => [$question->id => $service->choices($this->respondent, $question)]),
        ];
    }
};
?>

@php
    $refuse = \App\Services\SurveyService::REFUSE;
    $choiceCls = 'flex min-h-11 cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-2 hover:bg-slate-50 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent';
@endphp

<div>
    <header class="mb-8">
        <p class="text-sm font-bold text-accent">{{ $respondent->Project->name }}</p>
        <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">Survei</h1>
        @unless ($done)
            <p class="mt-2 text-slate-600">Halo {{ $respondent->name }}, terima kasih sudah bersedia mengisi. Jawaban Anda hanya dipakai untuk kepentingan survei ini.</p>
        @endunless
    </header>

    @if ($done)
        <div class="rounded-2xl border border-teal-200 bg-teal-50 p-6 text-teal-900" role="status">
            <h2 class="text-lg font-extrabold">Terima kasih!</h2>
            <p class="mt-1">Jawaban Anda sudah kami terima. Anda dapat menutup halaman ini.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6" novalidate>
            @error('survey')
                <p class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">{{ $message }}</p>
            @enderror

            @foreach ($questions as $question)
                @php
                    $number = $loop->iteration;
                    $errorKey = 'answers.'.$question->id;
                @endphp
                <fieldset wire:key="soal-{{ $question->id }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                    <legend class="sr-only">Pertanyaan {{ $number }}</legend>
                    <p class="font-bold">{{ $number }}. {{ $question->text }}@if ($question->is_required)<span class="text-rose-700"> *</span>@endif</p>
                    @if ($question->description)<p class="mt-1 text-sm text-slate-600">{{ $question->description }}</p>@endif

                    <div class="mt-4 space-y-2">
                        @if ($question->type === \App\Models\Question::TYPE_TEXT)
                            <label for="jawab-{{ $question->id }}" class="sr-only">Jawaban untuk pertanyaan {{ $number }}</label>
                            <textarea id="jawab-{{ $question->id }}" rows="3" wire:model="answers.{{ $question->id }}" maxlength="{{ \App\Services\SurveyService::MAX_TEXT_LENGTH }}" class="w-full rounded-2xl border border-slate-300 bg-white p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"></textarea>
                        @else
                            @php
                                $multiple = $question->type === \App\Models\Question::TYPE_MULTIPLE;
                            @endphp
                            @foreach ($choices[$question->id] as $choice)
                                <label class="{{ $choiceCls }}">
                                    <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="soal-{{ $question->id }}" value="{{ $choice }}" wire:model.live="answers.{{ $question->id }}" class="h-5 w-5 accent-accent">
                                    <span>{{ $choice }}</span>
                                </label>
                            @endforeach
                            @if ($question->has_refuse_option)
                                <label class="{{ $choiceCls }}">
                                    <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="soal-{{ $question->id }}" value="{{ $refuse }}" wire:model.live="answers.{{ $question->id }}" class="h-5 w-5 accent-accent">
                                    <span>Tidak mau menjawab</span>
                                </label>
                            @endif
                        @endif
                    </div>
                    @error($errorKey) <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
                </fieldset>
            @endforeach

            @if ($questions->isEmpty())
                <p class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-600">Survei ini belum memiliki pertanyaan.</p>
            @else
                <button type="submit" wire:loading.attr="disabled" wire:target="submit" class="min-h-11 w-full rounded-2xl bg-accent px-5 font-bold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">Kirim jawaban</button>
            @endif
        </form>
    @endif
</div>
