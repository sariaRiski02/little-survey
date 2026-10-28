<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Question;
use App\Models\RespondentAnswer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private StatistikService $statistikService) {}

    /**
     * @return array{population: int|null, respondents: int, candidates: int, filled: int, notFilled: int, responseRate: float}
     */
    public function summary(Project $project): array
    {
        $respondents = $project->Respondent()->count();
        $filled = $project->Respondent()->whereNotNull('filled_at')->count();
        $population = $project->isFullDptMode() ? $project->Dpt()->count() : $project->sample_population;

        return [
            'population' => $population,
            'respondents' => $respondents,
            'candidates' => $project->Candidate()->count(),
            'filled' => $filled,
            'notFilled' => $respondents - $filled,
            'responseRate' => $respondents > 0 ? round($filled / $respondents * 100, 1) : 0.0,
        ];
    }

    public function lastFilledAt(Project $project): ?Carbon
    {
        $value = $project->Respondent()->max('filled_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Fill progress per wilayah (the number of respondents is the quota), or overall without wilayah.
     *
     * @return list<array{nama: string, kuota: int, terisi: int, persen: float}>
     */
    public function progress(Project $project): array
    {
        $rows = $project->Respondent()
            ->selectRaw('wilayah, count(*) as kuota, count(filled_at) as terisi')
            ->groupBy('wilayah')
            ->toBase()
            ->get();

        if (! $project->usesWilayah()) {
            $quota = (int) $rows->sum('kuota');
            $filled = (int) $rows->sum('terisi');

            return [['nama' => 'Total', 'kuota' => $quota, 'terisi' => $filled, 'persen' => $this->percent($filled, $quota)]];
        }

        return $rows
            ->sortBy(fn ($row) => $row->wilayah === null || $row->wilayah === '' ? "\u{10FFFF}" : $row->wilayah)
            ->map(fn ($row) => [
                'nama' => filled($row->wilayah) ? $row->wilayah : 'Tanpa '.$project->wilayah_label,
                'kuota' => (int) $row->kuota,
                'terisi' => (int) $row->terisi,
                'persen' => $this->percent((int) $row->terisi, (int) $row->kuota),
            ])
            ->values()
            ->all();
    }

    public function candidateQuestion(Project $project): ?Question
    {
        return $project->Question()->where('type', Question::TYPE_CANDIDATE)->first();
    }

    /**
     * Answers to one question with the wilayah of whoever gave them. Only respondents who filled in have answers.
     *
     * @return Collection<int, RespondentAnswer>
     */
    public function answers(Project $project, Question $question): Collection
    {
        return RespondentAnswer::query()
            ->join('respondents', 'respondents.id', '=', 'respondent_answers.respondent_id')
            ->where('respondents.project_id', $project->id)
            ->where('respondent_answers.question_id', $question->id)
            ->get(['respondent_answers.values', 'respondent_answers.is_refused', 'respondents.wilayah']);
    }

    /**
     * Candidate comparison taken from the first "pilih kandidat" question.
     * Returns null when the project has no such question.
     *
     * @return array{
     *     question: Question,
     *     n: int,
     *     weighted: bool,
     *     canWeight: bool,
     *     candidates: list<array{nama: string, warna: string, jumlah: int, persen: float}>,
     *     others: list<array{nama: string, persen: float}>,
     *     galat: float|null,
     *     galatTarget: float,
     *     kepercayaan: int,
     *     selisih: float,
     *     unggul: string|null,
     *     perWilayah: list<array{nama: string, n: int, persen: float|null}>
     * }|null
     */
    public function results(Project $project, bool $weighted = false): ?array
    {
        $question = $this->candidateQuestion($project);

        if (! $question) {
            return null;
        }

        $answers = $this->answers($project, $question);

        $candidates = $project->Candidate()->orderByRaw('number is null')->orderBy('number')->orderBy('name')->get();
        $n = $answers->count();

        $dptPerWilayah = $project->usesWilayah() && $project->isFullDptMode()
            ? $project->Dpt()->selectRaw('wilayah, count(*) as jumlah')->groupBy('wilayah')->toBase()->pluck('jumlah', 'wilayah')
            : collect();
        $canWeight = $dptPerWilayah->isNotEmpty() && $n > 0;
        $useWeights = $weighted && $canWeight;

        $shares = $useWeights ? $this->weightedShares($answers, $dptPerWilayah) : $this->rawShares($answers);

        $rows = $candidates->map(function ($candidate) use ($shares) {
            $key = ucwords($candidate->name);

            return [
                'nama' => $key,
                'warna' => $candidate->color ?: '#64748B',
                'jumlah' => $shares['counts'][$key] ?? 0,
                'persen' => round($shares['percent'][$key] ?? 0, 1),
            ];
        })->all();

        $ranked = collect($rows)->sortByDesc('persen')->values();
        $selisih = round(($ranked[0]['persen'] ?? 0) - ($ranked[1]['persen'] ?? 0), 1);
        $galat = $this->actualMargin($project, $n);

        return [
            'question' => $question,
            'n' => $n,
            'weighted' => $useWeights,
            'canWeight' => $canWeight,
            'candidates' => $rows,
            'others' => $shares['refused'] > 0 || $question->has_refuse_option
                ? [['nama' => 'Tidak mau menjawab', 'persen' => round($shares['refusedPercent'], 1)]]
                : [],
            'galat' => $galat,
            'galatTarget' => (float) ($project->sample_margin_error ?? StatistikService::DEFAULTS['margin_error']),
            'kepercayaan' => (int) ($project->sample_confidence ?? StatistikService::DEFAULTS['confidence']),
            'selisih' => $selisih,
            'unggul' => $n > 0 && $galat !== null && $selisih > $galat && $ranked->isNotEmpty() ? $ranked[0]['nama'] : null,
            'perWilayah' => $this->perWilayah($project, $answers, $ranked[0]['nama'] ?? null),
        ];
    }

    /**
     * Margin of error of the estimate with p = 0.5 and finite population correction, in percent.
     */
    public function actualMargin(Project $project, int $n): ?float
    {
        if ($n < 1) {
            return null;
        }

        $z = StatistikService::CONFIDENCE_LEVELS[(int) ($project->sample_confidence ?? 95)] ?? StatistikService::CONFIDENCE_LEVELS[95];
        $population = $this->statistikService->population($project);
        $correction = $population > 1 ? max(0, ($population - $n) / ($population - 1)) : 1;

        return round($z * sqrt(0.25 / $n) * sqrt($correction) * 100, 2);
    }

    /**
     * @param  Collection<int, RespondentAnswer>  $answers
     * @return array{counts: array<string, int>, percent: array<string, float>, refused: int, refusedPercent: float}
     */
    private function rawShares(Collection $answers): array
    {
        $counts = [];
        $refused = 0;

        foreach ($answers as $answer) {
            if ($answer->is_refused) {
                $refused++;
            } elseif (isset($answer->values[0])) {
                $counts[$answer->values[0]] = ($counts[$answer->values[0]] ?? 0) + 1;
            }
        }

        $n = max(1, $answers->count());

        return [
            'counts' => $counts,
            'percent' => array_map(fn ($count) => $count / $n * 100, $counts),
            'refused' => $refused,
            'refusedPercent' => $refused / $n * 100,
        ];
    }

    /**
     * Post-stratification: each wilayah counts in proportion to its DPT size instead of its number of answers.
     *
     * @param  Collection<int, RespondentAnswer>  $answers
     * @param  Collection<string|null, int>  $dptPerWilayah
     * @return array{counts: array<string, int>, percent: array<string, float>, refused: int, refusedPercent: float}
     */
    private function weightedShares(Collection $answers, Collection $dptPerWilayah): array
    {
        $raw = $this->rawShares($answers);
        $groups = $answers->groupBy(fn ($answer) => (string) $answer->wilayah);
        $dptTotal = $groups->keys()->sum(fn ($wilayah) => (int) ($dptPerWilayah[$wilayah] ?? 0));

        if ($dptTotal === 0) {
            return $raw;
        }

        $percent = [];
        $refusedPercent = 0.0;

        foreach ($groups as $wilayah => $group) {
            $weight = (int) ($dptPerWilayah[$wilayah] ?? 0) / $dptTotal;
            $shares = $this->rawShares($group);

            foreach ($shares['percent'] as $name => $value) {
                $percent[$name] = ($percent[$name] ?? 0) + $value * $weight;
            }

            $refusedPercent += $shares['refusedPercent'] * $weight;
        }

        return ['counts' => $raw['counts'], 'percent' => $percent, 'refused' => $raw['refused'], 'refusedPercent' => $refusedPercent];
    }

    /**
     * Raw share of the leading candidate within each wilayah.
     *
     * @param  Collection<int, RespondentAnswer>  $answers
     * @return list<array{nama: string, n: int, persen: float|null}>
     */
    private function perWilayah(Project $project, Collection $answers, ?string $leader): array
    {
        if (! $project->usesWilayah() || $leader === null) {
            return [];
        }

        $groups = $answers->groupBy(fn ($answer) => (string) $answer->wilayah);

        return collect($this->progress($project))->map(function (array $row) use ($project, $groups, $leader) {
            $key = $row['nama'] === 'Tanpa '.$project->wilayah_label ? '' : $row['nama'];
            $group = $groups->get($key, collect());
            $votes = $group->filter(fn ($answer) => ! $answer->is_refused && ($answer->values[0] ?? null) === $leader)->count();

            return ['nama' => $row['nama'], 'n' => $group->count(), 'persen' => $group->isEmpty() ? null : round($votes / $group->count() * 100, 1)];
        })->all();
    }

    private function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
