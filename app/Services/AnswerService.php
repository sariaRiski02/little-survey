<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AnswerService
{
    public const REFUSED_LABEL = 'Tidak mau menjawab';

    /**
     * @return Collection<int, Question>
     */
    public function questions(Project $project): Collection
    {
        return $project->Question()->get();
    }

    /**
     * Respondents who have filled the survey, with their answers.
     */
    public function paginate(Project $project, ?string $search = null, ?string $wilayah = null, int $perPage = 10): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return $this->filled($project, $wilayah)
            ->with('Answer')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->where(function ($q) use ($like, $search) {
                    $q->where('name', 'like', $like);

                    if (preg_match('/^r?-?0*(\d+)$/i', $search, $match)) {
                        $q->orWhere('number', (int) $match[1]);
                    }
                });
            })
            ->orderBy('number')
            ->paginate($perPage);
    }

    public function count(Project $project, ?string $wilayah = null): int
    {
        return $this->filled($project, $wilayah)->count();
    }

    /**
     * The answer of a respondent to a question as readable text. Empty when the question was not answered or not shown.
     */
    public function answerText(Respondent $respondent, Question $question): string
    {
        $answer = $respondent->Answer->firstWhere('question_id', $question->id);

        if (! $answer) {
            return '';
        }

        if ($answer->is_refused) {
            return self::REFUSED_LABEL;
        }

        return implode('; ', $answer->values ?? []);
    }

    /**
     * Rows for the Excel export: one row per respondent who filled the survey, one column per question.
     * The respondent's NIK and phone number are left out on purpose.
     *
     * @return iterable<int, list<string>>
     */
    public function exportRows(Project $project, ?string $wilayah = null): iterable
    {
        $questions = $this->questions($project);
        $usesWilayah = $project->usesWilayah();

        yield [
            'Kode',
            'Nama',
            ...($usesWilayah ? [$project->wilayah_label] : []),
            'Waktu mengisi',
            ...$questions->map(fn (Question $question) => $question->position.'. '.$question->text)->all(),
        ];

        foreach ($this->filled($project, $wilayah)->with('Answer')->orderBy('number')->lazyById(500) as $respondent) {
            yield [
                $respondent->code(),
                $respondent->name,
                ...($usesWilayah ? [(string) $respondent->wilayah] : []),
                $respondent->filled_at->format('Y-m-d H:i'),
                ...$questions->map(fn (Question $question) => $this->answerText($respondent, $question))->all(),
            ];
        }
    }

    public function writeExport(Project $project, string $path, ?string $wilayah = null): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($this->exportRows($project, $wilayah) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    /**
     * @return HasMany<Respondent, Project>
     */
    private function filled(Project $project, ?string $wilayah): HasMany
    {
        return $project->Respondent()
            ->whereNotNull('filled_at')
            ->when(filled($wilayah), fn ($query) => $query->where('wilayah', $wilayah));
    }
}
