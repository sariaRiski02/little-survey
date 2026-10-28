<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RespondentAnswer;
use Illuminate\Support\Collection;

class SignificanceService
{
    /** Chi-square needs expected counts of at least this value to be reliable. */
    private const MIN_EXPECTED = 5;

    public function __construct(private DashboardService $dashboard) {}

    /**
     * Significance tests of the candidate question. Raw (unweighted) counts are used.
     * Returns null when the project has no candidate question.
     *
     * @return array{
     *     confidence: int,
     *     alpha: float,
     *     n: int,
     *     votes: int,
     *     candidates: list<array{nama: string, warna: string, jumlah: int, persen: float, ciLow: float, ciHigh: float, vsLeader: array{p: float|null, significant: bool|null}|null}>,
     *     difference: array{leader: string, runnerUp: string, selisih: float, z: float|null, p: float|null, significant: bool, conclusion: string}|null,
     *     goodness: array{chi2: float, df: int, p: float, significant: bool, reliable: bool, conclusion: string}|null,
     *     independence: array{chi2: float, df: int, p: float, significant: bool, reliable: bool, cramersV: float, conclusion: string}|null,
     *     notes: array<string, string>,
     *     conclusion: string
     * }|null
     */
    public function analyze(Project $project): ?array
    {
        $question = $this->dashboard->candidateQuestion($project);

        if (! $question) {
            return null;
        }

        $answers = $this->dashboard->answers($project, $question);
        $candidates = $project->Candidate()->orderByRaw('number is null')->orderBy('number')->orderBy('name')->get();

        $confidence = (int) ($project->sample_confidence ?? StatistikService::DEFAULTS['confidence']);
        $alpha = round(1 - $confidence / 100, 4);
        $z = Statistics::zForConfidence($confidence);
        $n = $answers->count();

        $counts = [];
        foreach ($answers as $answer) {
            if (! $answer->is_refused && isset($answer->values[0])) {
                $counts[$answer->values[0]] = ($counts[$answer->values[0]] ?? 0) + 1;
            }
        }

        $rows = $candidates->map(function ($candidate) use ($counts, $n, $z) {
            $name = ucwords($candidate->name);
            $count = $counts[$name] ?? 0;
            [$low, $high] = Statistics::wilsonInterval($count, $n, $z);

            return [
                'nama' => $name,
                'warna' => $candidate->color ?: '#64748B',
                'jumlah' => $count,
                'persen' => $n > 0 ? round($count / $n * 100, 1) : 0.0,
                'ciLow' => round($low * 100, 1),
                'ciHigh' => round($high * 100, 1),
                'vsLeader' => null,
            ];
        })->sortByDesc('jumlah')->values()->all();

        $result = [
            'confidence' => $confidence,
            'alpha' => $alpha,
            'n' => $n,
            'votes' => array_sum($counts),
            'candidates' => $rows,
            'difference' => null,
            'goodness' => null,
            'independence' => null,
            'notes' => [],
            'conclusion' => 'Belum ada jawaban, uji signifikansi belum dapat dihitung.',
        ];

        if ($n === 0 || count($rows) < 2) {
            if ($n > 0) {
                $result['notes']['difference'] = 'Dibutuhkan minimal dua kandidat.';
            }

            return $result;
        }

        foreach ($rows as $index => $row) {
            if ($index > 0) {
                $p = $this->differencePValue($rows[0]['jumlah'], $row['jumlah'], $n);
                $rows[$index]['vsLeader'] = ['p' => $p, 'significant' => $p === null ? null : $p < $alpha];
            }
        }
        $result['candidates'] = $rows;

        $result['difference'] = $this->difference($rows[0], $rows[1], $n, $alpha);
        $result['goodness'] = $this->goodnessOfFit(array_column($rows, 'jumlah'), $alpha, $result['notes']);
        $result['independence'] = $project->usesWilayah() ? $this->independence($project, $answers, $rows, $alpha, $result['notes']) : null;
        $result['conclusion'] = $this->conclusion($result);

        return $result;
    }

    private function differencePValue(int $count1, int $count2, int $n): ?float
    {
        $z = Statistics::differenceZ($count1, $count2, $n);

        if ($z === null) {
            return $count1 === $count2 ? 1.0 : 0.0;
        }

        return Statistics::twoSidedPValue($z);
    }

    /**
     * Two-proportion z test: do the top two candidates differ?
     *
     * @param  array{nama: string, jumlah: int, persen: float}  $leader
     * @param  array{nama: string, jumlah: int, persen: float}  $runnerUp
     */
    private function difference(array $leader, array $runnerUp, int $n, float $alpha): array
    {
        $z = Statistics::differenceZ($leader['jumlah'], $runnerUp['jumlah'], $n);
        $p = $this->differencePValue($leader['jumlah'], $runnerUp['jumlah'], $n);
        $significant = $p < $alpha;
        $gap = round($leader['persen'] - $runnerUp['persen'], 1);

        return [
            'leader' => $leader['nama'],
            'runnerUp' => $runnerUp['nama'],
            'selisih' => $gap,
            'z' => $z === null ? null : round($z, 3),
            'p' => $p,
            'significant' => $significant,
            'conclusion' => $significant
                ? $leader['nama'].' unggul atas '.$runnerUp['nama'].' secara signifikan (selisih '.$this->number($gap).' poin).'
                : 'Selisih '.$leader['nama'].' dan '.$runnerUp['nama'].' ('.$this->number($gap).' poin) tidak signifikan secara statistik; belum bisa disimpulkan ada yang unggul.',
        ];
    }

    /**
     * Chi-square goodness of fit against an even split of the votes between the candidates.
     *
     * @param  list<int>  $counts
     * @param  array<string, string>  $notes
     */
    private function goodnessOfFit(array $counts, float $alpha, array &$notes): ?array
    {
        $votes = array_sum($counts);
        $k = count($counts);

        if ($votes < 1 || $k < 2) {
            $notes['goodness'] = 'Belum ada suara untuk kandidat.';

            return null;
        }

        $expected = $votes / $k;
        $chi2 = array_sum(array_map(fn (int $count) => ($count - $expected) ** 2 / $expected, $counts));
        $p = Statistics::chiSquareSurvival($chi2, $k - 1);
        $reliable = $expected >= self::MIN_EXPECTED;

        if (! $reliable) {
            $notes['goodness'] = 'Frekuensi harapan kurang dari '.self::MIN_EXPECTED.' per kandidat, hasil hanya perkiraan kasar.';
        }

        return [
            'chi2' => round($chi2, 3),
            'df' => $k - 1,
            'p' => $p,
            'significant' => $p < $alpha,
            'reliable' => $reliable,
            'conclusion' => $p < $alpha
                ? 'Dukungan antar kandidat berbeda nyata dari pembagian yang merata.'
                : 'Dukungan antar kandidat belum berbeda nyata dari pembagian yang merata.',
        ];
    }

    /**
     * Chi-square test of independence between candidate and wilayah.
     *
     * @param  Collection<int, RespondentAnswer>  $answers
     * @param  list<array{nama: string, jumlah: int}>  $rows
     * @param  array<string, string>  $notes
     */
    private function independence(Project $project, $answers, array $rows, float $alpha, array &$notes): ?array
    {
        $table = [];

        foreach ($answers as $answer) {
            if ($answer->is_refused || ! isset($answer->values[0])) {
                continue;
            }

            $wilayah = (string) $answer->wilayah;
            $table[$wilayah][$answer->values[0]] = ($table[$wilayah][$answer->values[0]] ?? 0) + 1;
        }

        $columns = array_values(array_filter(array_column($rows, 'nama'), fn ($name) => array_sum(array_column($table, $name)) > 0));
        $table = array_filter($table, fn ($row) => array_sum($row) > 0);

        if (count($table) < 2 || count($columns) < 2) {
            $notes['independence'] = 'Dibutuhkan minimal dua '.$project->wilayah_label.' dan dua kandidat yang memiliki suara.';

            return null;
        }

        $total = array_sum(array_map('array_sum', $table));
        $columnTotals = array_map(fn ($name) => array_sum(array_column($table, $name)), array_combine($columns, $columns));
        $chi2 = 0.0;
        $lowCells = 0;
        $cells = 0;

        foreach ($table as $row) {
            $rowTotal = array_sum($row);

            foreach ($columns as $name) {
                $expected = $rowTotal * $columnTotals[$name] / $total;
                $chi2 += (($row[$name] ?? 0) - $expected) ** 2 / $expected;
                $cells++;
                $lowCells += $expected < self::MIN_EXPECTED ? 1 : 0;
            }
        }

        $df = (count($table) - 1) * (count($columns) - 1);
        $p = Statistics::chiSquareSurvival($chi2, $df);
        $reliable = $lowCells / $cells <= 0.2;

        if (! $reliable) {
            $notes['independence'] = 'Lebih dari 20% sel memiliki frekuensi harapan di bawah '.self::MIN_EXPECTED.'; hasil hanya perkiraan kasar.';
        }

        return [
            'chi2' => round($chi2, 3),
            'df' => $df,
            'p' => $p,
            'significant' => $p < $alpha,
            'reliable' => $reliable,
            'cramersV' => round(sqrt($chi2 / ($total * min(count($table) - 1, count($columns) - 1))), 3),
            'conclusion' => $p < $alpha
                ? 'Pilihan kandidat berbeda nyata antar '.$project->wilayah_label.'.'
                : 'Pilihan kandidat tidak berbeda nyata antar '.$project->wilayah_label.'.',
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function conclusion(array $result): string
    {
        return $result['difference']['conclusion'];
    }

    private function number(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }
}
