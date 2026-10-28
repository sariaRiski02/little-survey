<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class QuestionService
{
    public const MINUTES_PER_QUESTION = 0.5;

    /** Types a branch can depend on: their answers are a fixed list of strings. */
    private const BRANCH_PARENT_TYPES = Question::OPTION_TYPES;

    /**
     * Questions of the "Survei Preferensi Pemilih" template. `branch` is the index of an earlier question.
     *
     * @var list<array<string, mixed>>
     */
    public const TEMPLATE = [
        [
            'text' => 'Apakah Anda berencana menggunakan hak pilih pada pemilihan mendatang?',
            'type' => Question::TYPE_SINGLE,
            'options' => ['Ya', 'Tidak', 'Belum tahu'],
            'is_required' => true,
            'has_refuse_option' => false,
        ],
        [
            'text' => 'Jika pemilihan diadakan hari ini, siapa yang akan Anda pilih?',
            'type' => Question::TYPE_CANDIDATE,
            'options' => null,
            'is_required' => true,
            'has_refuse_option' => true,
            'branch' => 0,
            'branch_answer' => 'Ya',
        ],
        [
            'text' => 'Seberapa yakin Anda dengan pilihan tersebut?',
            'type' => Question::TYPE_SCALE,
            'options' => ['Sangat tidak yakin', 'Tidak yakin', 'Cukup yakin', 'Yakin', 'Sangat yakin'],
            'is_required' => true,
            'has_refuse_option' => false,
        ],
        [
            'text' => 'Isu apa yang paling Anda harapkan menjadi prioritas?',
            'type' => Question::TYPE_TEXT,
            'options' => null,
            'is_required' => false,
            'has_refuse_option' => false,
        ],
    ];

    /**
     * @return Collection<int, Question>
     */
    public function list(Project $project): Collection
    {
        return $project->Question()->get();
    }

    public function estimatedMinutes(int $questionCount): int
    {
        return max(1, (int) ceil($questionCount * self::MINUTES_PER_QUESTION));
    }

    /**
     * Answer choices shown to the respondent (candidate names for candidate questions).
     *
     * @return list<string>
     */
    public function choices(Project $project, Question $question): array
    {
        if ($question->type === Question::TYPE_CANDIDATE) {
            return $project->Candidate()->orderByRaw('number is null')->orderBy('number')->orderBy('name')->pluck('name')->map(fn ($name) => ucwords($name))->all();
        }

        return $question->options ?? [];
    }

    /**
     * Split one-choice-per-line input into trimmed, non-empty lines.
     *
     * @return list<string>
     */
    public function parseOptions(?string $input): array
    {
        return collect(preg_split('/\R/', (string) $input))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validator(Project $project, array $data): Validator
    {
        $type = $data['type'] ?? null;
        $options = $this->parseOptions($data['options'] ?? null);
        $maxOptions = $type === Question::TYPE_SCALE ? 10 : 20;

        $validator = ValidatorFacade::make($data, [
            'text' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'is_required' => ['boolean'],
            'has_refuse_option' => ['boolean'],
            'branch_question_id' => ['nullable', 'integer', Rule::exists('questions', 'id')->where('project_id', $project->id)],
            'branch_answer' => ['nullable', 'string', 'max:255'],
        ], [
            'text.required' => 'Teks pertanyaan wajib diisi.',
            'text.max' => 'Teks pertanyaan maksimal 1.000 karakter.',
            'description.max' => 'Keterangan maksimal 255 karakter.',
            'type.required' => 'Tipe wajib dipilih.',
            'type.in' => 'Tipe pertanyaan tidak valid.',
            'branch_question_id.exists' => 'Pertanyaan acuan percabangan tidak ditemukan.',
        ]);

        return $validator->after(function (Validator $validator) use ($project, $type, $options, $maxOptions, $data) {
            if (in_array($type, Question::OPTION_TYPES, true)) {
                if (count($options) < 2) {
                    $validator->errors()->add('options', 'Isi minimal 2 pilihan jawaban.');
                } elseif (count($options) > $maxOptions) {
                    $validator->errors()->add('options', 'Maksimal '.$maxOptions.' pilihan jawaban.');
                } elseif (collect($options)->contains(fn ($option) => mb_strlen($option) > 100)) {
                    $validator->errors()->add('options', 'Tiap pilihan maksimal 100 karakter.');
                } elseif (count(array_unique(array_map('mb_strtolower', $options))) !== count($options)) {
                    $validator->errors()->add('options', 'Pilihan jawaban tidak boleh sama.');
                }
            }

            if ($type === Question::TYPE_CANDIDATE && $project->Candidate()->count() < 2) {
                $validator->errors()->add('type', 'Tambahkan minimal 2 kandidat di menu Kandidat terlebih dahulu.');
            }

            $branchId = $data['branch_question_id'] ?? null;

            if (blank($branchId) || $validator->errors()->has('branch_question_id')) {
                return;
            }

            $parent = $project->Question()->find($branchId);

            if (! $parent || ! in_array($parent->type, self::BRANCH_PARENT_TYPES, true)) {
                $validator->errors()->add('branch_question_id', 'Percabangan hanya bisa mengacu ke pertanyaan berpilihan jawaban.');

                return;
            }

            if (blank($data['branch_answer'] ?? null)) {
                $validator->errors()->add('branch_answer', 'Pilih jawaban yang memicu pertanyaan ini.');
            } elseif (! in_array($data['branch_answer'], $parent->options ?? [], true)) {
                $validator->errors()->add('branch_answer', 'Jawaban tidak ada pada pertanyaan acuan.');
            }
        });
    }

    /**
     * The new question is appended at the end, so a branch can only ever point to an earlier question.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data): Question
    {
        $this->ensureUnlocked($project);

        $validated = $this->validator($project, $data)->validate();
        $type = $validated['type'];
        $hasOptions = in_array($type, Question::OPTION_TYPES, true);
        $hasBranch = filled($validated['branch_question_id'] ?? null);

        return DB::transaction(fn () => $project->Question()->create([
            'position' => (int) $project->Question()->max('position') + 1,
            'text' => trim($validated['text']),
            'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'type' => $type,
            'options' => $hasOptions ? $this->parseOptions($data['options']) : null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'has_refuse_option' => $type !== Question::TYPE_TEXT && (bool) ($validated['has_refuse_option'] ?? false),
            'branch_question_id' => $hasBranch ? (int) $validated['branch_question_id'] : null,
            'branch_answer' => $hasBranch ? $validated['branch_answer'] : null,
        ]));
    }

    /**
     * Swap a question with its neighbour. Rejected if it would put a question before the one it branches from.
     */
    public function move(Project $project, int|string $id, string $direction): void
    {
        $this->ensureUnlocked($project);

        DB::transaction(function () use ($project, $id, $direction) {
            $questions = $project->Question()->get()->values();
            $index = $questions->search(fn (Question $question) => $question->id === (int) $id);

            if ($index === false) {
                abort(404);
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if (! isset($questions[$target])) {
                return;
            }

            $reordered = $questions->all();
            [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

            $positions = collect($reordered)->pluck('id')->flip();
            foreach ($reordered as $question) {
                if ($question->branch_question_id && $positions[$question->branch_question_id] >= $positions[$question->id]) {
                    throw ValidationException::withMessages([
                        'project' => 'Urutan tidak valid: pertanyaan percabangan harus tetap berada setelah pertanyaan acuannya.',
                    ]);
                }
            }

            foreach ($reordered as $position => $question) {
                if ($question->position !== $position + 1) {
                    $question->update(['position' => $position + 1]);
                }
            }
        });
    }

    /**
     * Delete a question; questions that branched from it become unconditional.
     */
    public function delete(Project $project, int|string $id): void
    {
        $this->ensureUnlocked($project);

        DB::transaction(function () use ($project, $id) {
            $question = $project->Question()->findOrFail($id);

            $project->Question()->where('branch_question_id', $question->id)->update(['branch_question_id' => null, 'branch_answer' => null]);
            $question->delete();

            $project->Question()->get()->each(function (Question $remaining, int $index) {
                if ($remaining->position !== $index + 1) {
                    $remaining->update(['position' => $index + 1]);
                }
            });
        });
    }

    /**
     * Append the template questions after the existing ones.
     *
     * @return int number of questions added
     */
    public function applyTemplate(Project $project): int
    {
        $this->ensureUnlocked($project);

        return DB::transaction(function () use ($project) {
            $position = (int) $project->Question()->max('position');
            $created = [];

            foreach (self::TEMPLATE as $index => $item) {
                $branch = isset($item['branch']) ? $created[$item['branch']] : null;

                $created[$index] = $project->Question()->create([
                    'position' => ++$position,
                    'text' => $item['text'],
                    'type' => $item['type'],
                    'options' => $item['options'],
                    'is_required' => $item['is_required'],
                    'has_refuse_option' => $item['has_refuse_option'],
                    'branch_question_id' => $branch?->id,
                    'branch_answer' => $branch ? $item['branch_answer'] : null,
                ]);
            }

            return count($created);
        });
    }

    /**
     * Questions cannot be changed once locked. The lock state is re-read from the database
     * to avoid stale component state.
     */
    public function ensureUnlocked(Project $project): void
    {
        if (Project::whereKey($project->getKey())->value('is_questions_locked')) {
            throw ValidationException::withMessages([
                'project' => 'Pertanyaan terkunci karena tautan survei sudah dikirim, sehingga tidak dapat diubah.',
            ]);
        }
    }
}
