<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use App\Models\RespondentAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyService
{
    /** Answer value sent when the respondent picks "Tidak mau menjawab". */
    public const REFUSE = '__refuse__';

    public const MAX_TEXT_LENGTH = 2000;

    public function __construct(private QuestionService $questionService, private RespondentService $respondentService) {}

    public function findByToken(string $token): Respondent
    {
        return Respondent::where('token', $token)->with('Project')->firstOrFail();
    }

    /**
     * @return Collection<int, Question>
     */
    public function questions(Project $project): Collection
    {
        return $project->Question()->get();
    }

    /**
     * Questions the respondent gets to see for the answers given so far.
     * A question with a branch shows only if its parent is visible and was answered with the trigger answer.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<int|string, mixed>  $answers
     * @return Collection<int, Question>
     */
    public function visibleQuestions(Collection $questions, array $answers): Collection
    {
        $visible = [];

        foreach ($questions as $question) {
            $visible[$question->id] = $question->branch_question_id === null
                || (($visible[$question->branch_question_id] ?? false)
                    && in_array($question->branch_answer, $this->selected($answers[$question->branch_question_id] ?? null), true));
        }

        return $questions->filter(fn (Question $question) => $visible[$question->id])->values();
    }

    /**
     * Choices shown to this respondent. Candidates are shuffled per respondent when the project randomizes them.
     *
     * @return list<string>
     */
    public function choices(Respondent $respondent, Question $question): array
    {
        $choices = $this->questionService->choices($respondent->Project, $question);

        if ($question->type === Question::TYPE_CANDIDATE && $respondent->Project->randomize_candidates) {
            usort($choices, fn (string $a, string $b) => strcmp(hash('xxh3', $respondent->id.':'.$a), hash('xxh3', $respondent->id.':'.$b)));
        }

        return $choices;
    }

    /**
     * Validate and store the answers, then mark the respondent as having filled the survey.
     * Answers of questions hidden by branching are ignored.
     *
     * @param  array<int|string, mixed>  $answers  question id => string|list<string>
     */
    public function submit(Respondent $respondent, array $answers): void
    {
        DB::transaction(function () use ($respondent, $answers) {
            $fresh = Respondent::whereKey($respondent->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->filled_at !== null) {
                throw ValidationException::withMessages(['survey' => 'Anda sudah mengisi survei ini.']);
            }

            $questions = $this->questions($fresh->Project);
            $rows = [];
            $errors = [];

            foreach ($this->visibleQuestions($questions, $answers) as $question) {
                $result = $this->normalize($fresh, $question, $answers[$question->id] ?? null);

                if (is_string($result)) {
                    $errors['answers.'.$question->id] = $result;
                } elseif ($result !== null) {
                    $rows[] = ['question_id' => $question->id] + $result;
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $now = now();

            foreach ($rows as $row) {
                RespondentAnswer::create([
                    'respondent_id' => $fresh->id,
                    'question_id' => $row['question_id'],
                    'values' => $row['values'],
                    'is_refused' => $row['is_refused'],
                ]);
            }

            if ($fresh->link_sent_at === null) {
                $this->respondentService->markSent($fresh->Project, $fresh->id);
            }

            $fresh->update(['filled_at' => $now]);
            $respondent->setRawAttributes($fresh->fresh()->getAttributes(), true);
        });
    }

    /**
     * @return array{values: list<string>, is_refused: bool}|string|null the normalized answer, an error message, or null when optional and empty
     */
    private function normalize(Respondent $respondent, Question $question, mixed $raw): array|string|null
    {
        $required = 'Pertanyaan ini wajib dijawab.';
        $invalid = 'Jawaban tidak valid.';
        $selected = $this->selected($raw);

        if ($question->type === Question::TYPE_TEXT) {
            $text = $selected[0] ?? '';

            if ($text === '') {
                return $question->is_required ? $required : null;
            }

            return mb_strlen($text) > self::MAX_TEXT_LENGTH
                ? 'Jawaban maksimal '.number_format(self::MAX_TEXT_LENGTH, 0, ',', '.').' karakter.'
                : ['values' => [$text], 'is_refused' => false];
        }

        if ($selected === []) {
            return $question->is_required ? $required : null;
        }

        if (in_array(self::REFUSE, $selected, true)) {
            if (! $question->has_refuse_option) {
                return $invalid;
            }

            return count($selected) === 1
                ? ['values' => [], 'is_refused' => true]
                : 'Pilih “Tidak mau menjawab” saja, atau pilihan lainnya.';
        }

        if ($question->type !== Question::TYPE_MULTIPLE && count($selected) > 1) {
            return $invalid;
        }

        if (array_diff($selected, $this->choices($respondent, $question)) !== []) {
            return $invalid;
        }

        return ['values' => $selected, 'is_refused' => false];
    }

    /**
     * Normalize a posted value into a list of distinct, non-empty trimmed strings.
     *
     * @return list<string>
     */
    private function selected(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];

        return collect($items)
            ->filter(fn ($item) => is_string($item) || is_int($item))
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn (string $item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
