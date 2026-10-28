<?php

namespace App\Services;

use App\Models\Dpt;
use App\Models\Project;
use App\Models\SampleDraw;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StatistikService
{
    /** Z-score per confidence level (%). */
    public const CONFIDENCE_LEVELS = [90 => 1.645, 95 => 1.96, 99 => 2.576];

    public const DEFAULTS = [
        'confidence' => 95,
        'margin_error' => 5.0,
        'proportion' => 0.5,
        'reserve' => 20,
    ];

    /**
     * Sampling parameters of the project, falling back to defaults.
     *
     * @return array{confidence: int, margin_error: float, proportion: float, reserve: int, population: int|null}
     */
    public function parameters(Project $project): array
    {
        return [
            'confidence' => (int) ($project->sample_confidence ?? self::DEFAULTS['confidence']),
            'margin_error' => (float) ($project->sample_margin_error ?? self::DEFAULTS['margin_error']),
            'proportion' => (float) ($project->sample_proportion ?? self::DEFAULTS['proportion']),
            'reserve' => (int) ($project->sample_reserve ?? self::DEFAULTS['reserve']),
            'population' => $project->sample_population,
        ];
    }

    /**
     * Population (N): the DPT size in full DPT mode, or the manually entered number otherwise.
     */
    public function population(Project $project): int
    {
        if ($project->isFullDptMode()) {
            return $project->Dpt()->count();
        }

        return (int) $project->sample_population;
    }

    /**
     * Accept Indonesian decimal commas and trim input before validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeInput(array $input): array
    {
        foreach (['margin_error', 'proportion'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = str_replace(',', '.', trim($input[$key]));
            }
        }

        foreach (['confidence', 'reserve', 'population'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }

        return $input;
    }

    public function validator(Project $project, array $input): Validator
    {
        $rules = [
            'confidence' => ['required', Rule::in(array_keys(self::CONFIDENCE_LEVELS))],
            'margin_error' => ['required', 'numeric', 'between:0.5,25'],
            'proportion' => ['required', 'numeric', 'between:0.01,0.99'],
            'reserve' => ['required', 'integer', 'between:0,90'],
        ];

        if (! $project->isFullDptMode()) {
            $rules['population'] = ['required', 'integer', 'min:1', 'max:2000000000'];
        }

        return ValidatorFacade::make($input, $rules, [
            'confidence.required' => 'Tingkat kepercayaan wajib dipilih.',
            'confidence.in' => 'Tingkat kepercayaan harus 90%, 95%, atau 99%.',
            'margin_error.required' => 'Margin of error wajib diisi.',
            'margin_error.numeric' => 'Margin of error harus berupa angka.',
            'margin_error.between' => 'Margin of error harus antara 0,5% dan 25%.',
            'proportion.required' => 'Proporsi wajib diisi.',
            'proportion.numeric' => 'Proporsi harus berupa angka.',
            'proportion.between' => 'Proporsi harus antara 0,01 dan 0,99.',
            'reserve.required' => 'Cadangan non-respons wajib diisi.',
            'reserve.integer' => 'Cadangan non-respons harus berupa bilangan bulat.',
            'reserve.between' => 'Cadangan non-respons harus antara 0% dan 90%.',
            'population.required' => 'Populasi wajib diisi.',
            'population.integer' => 'Populasi harus berupa bilangan bulat.',
            'population.min' => 'Populasi minimal 1.',
            'population.max' => 'Populasi terlalu besar.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveParameters(Project $project, array $input): Project
    {
        $data = $this->validator($project, $this->normalizeInput($input))->validate();

        $attributes = [
            'sample_confidence' => (int) $data['confidence'],
            'sample_margin_error' => (float) $data['margin_error'],
            'sample_proportion' => (float) $data['proportion'],
            'sample_reserve' => (int) $data['reserve'],
        ];

        if (! $project->isFullDptMode()) {
            $attributes['sample_population'] = (int) $data['population'];
        }

        $project->update($attributes);

        return $project;
    }

    /**
     * Cochran's formula with finite population correction.
     *
     * @param  array{confidence: int, margin_error: float, proportion: float, reserve: int}  $params
     * @return array{population: int, sampleSize: int, invitations: int, steps: list<array{judul: string, rumus: string, hasil: string}>}|null
     */
    public function calculate(int $population, array $params): ?array
    {
        if ($population < 1) {
            return null;
        }

        $z = self::CONFIDENCE_LEVELS[$params['confidence']] ?? self::CONFIDENCE_LEVELS[95];
        $e = $params['margin_error'] / 100;
        $p = $params['proportion'];
        $reserve = $params['reserve'] / 100;

        $n0 = ($z ** 2) * $p * (1 - $p) / ($e ** 2);
        $corrected = $n0 / (1 + ($n0 - 1) / $population);
        $sampleSize = min($population, (int) ceil(round($corrected, 6)));
        $invitations = min($population, (int) ceil(round($sampleSize / (1 - $reserve), 6)));

        return [
            'population' => $population,
            'sampleSize' => $sampleSize,
            'invitations' => $invitations,
            'steps' => [
                [
                    'judul' => 'Nilai Z',
                    'rumus' => 'Tingkat kepercayaan '.$params['confidence'].'% pada tabel distribusi normal',
                    'hasil' => $this->decimal($z, 3),
                ],
                [
                    'judul' => 'Ukuran sampel tanpa koreksi (n₀)',
                    'rumus' => 'n₀ = Z² × p × (1 − p) / e² = '.$this->decimal($z, 3).'² × '.$this->decimal($p, 2).' × '.$this->decimal(1 - $p, 2).' / '.$this->decimal($e, 3).'²',
                    'hasil' => $this->decimal($n0, 2),
                ],
                [
                    'judul' => 'Koreksi populasi terbatas (n)',
                    'rumus' => 'n = n₀ / (1 + (n₀ − 1) / N) = '.$this->decimal($n0, 2).' / (1 + ('.$this->decimal($n0, 2).' − 1) / '.number_format($population, 0, ',', '.').'), dibulatkan ke atas',
                    'hasil' => number_format($sampleSize, 0, ',', '.'),
                ],
                [
                    'judul' => 'Jumlah undangan',
                    'rumus' => 'n / (1 − cadangan) = '.number_format($sampleSize, 0, ',', '.').' / (1 − '.$this->decimal($reserve, 2).'), dibulatkan ke atas',
                    'hasil' => number_format($invitations, 0, ',', '.'),
                ],
            ],
        ];
    }

    /**
     * Proportional allocation of the sample and invitations per wilayah.
     * Sample quotas use the largest remainder method so they add up exactly to the sample size;
     * invitation quotas are rounded up per wilayah, so their total may exceed the invitation count.
     *
     * @param  array{sampleSize: int, invitations: int, population: int}  $calculation
     * @return array{rows: list<array{key: string|null, nama: string, dpt: int, persen: float, sampel: int, undangan: int}>, total: array{dpt: int, persen: float, sampel: int, undangan: int}}
     */
    public function allocation(Project $project, array $calculation): array
    {
        $groups = $project->Dpt()
            ->select('wilayah', DB::raw('count(*) as jumlah'))
            ->groupBy('wilayah')
            ->get()
            ->map(fn ($row) => ['key' => filled($row->wilayah) ? $row->wilayah : null, 'dpt' => (int) $row->jumlah])
            ->sortBy(fn ($group) => $group['key'] ?? "\u{10FFFF}")
            ->values();

        $population = $calculation['population'];
        $sampleSize = $calculation['sampleSize'];
        $invitations = $calculation['invitations'];

        $quotas = $groups->map(function ($group) use ($population, $sampleSize) {
            $exact = $sampleSize * $group['dpt'] / $population;

            return ['floor' => (int) floor($exact), 'remainder' => $exact - floor($exact)];
        });
        $leftover = $sampleSize - $quotas->sum('floor');
        $bonus = $quotas
            ->map(fn ($quota, $index) => ['index' => $index, 'remainder' => $quota['remainder']])
            ->sortByDesc('remainder')
            ->take(max(0, $leftover))
            ->pluck('index')
            ->flip();

        $rows = $groups->map(fn ($group, $index) => [
            'key' => $group['key'],
            'nama' => $group['key'] ?? 'Tanpa '.($project->wilayah_label ?: 'wilayah'),
            'dpt' => $group['dpt'],
            'persen' => round($group['dpt'] / $population * 100, 1),
            'sampel' => $quotas[$index]['floor'] + ($bonus->has($index) ? 1 : 0),
            'undangan' => min($group['dpt'], intdiv($invitations * $group['dpt'] + $population - 1, $population)),
        ])->all();

        return [
            'rows' => $rows,
            'total' => [
                'dpt' => array_sum(array_column($rows, 'dpt')),
                'persen' => round(array_sum(array_column($rows, 'persen')), 1),
                'sampel' => array_sum(array_column($rows, 'sampel')),
                'undangan' => array_sum(array_column($rows, 'undangan')),
            ],
        ];
    }

    public function hasBeenDrawn(Project $project): bool
    {
        return $project->SampleDraws()->exists();
    }

    /**
     * First random draw. Locks the DPT and the candidate list.
     */
    public function draw(Project $project, ?int $seed = null): SampleDraw
    {
        return $this->performDraw($project, null, $seed);
    }

    /**
     * Draw again, replacing the previous respondents. A reason is required and kept in the history.
     */
    public function redraw(Project $project, string $reason, ?int $seed = null): SampleDraw
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan undi ulang wajib diisi.']);
        }

        return $this->performDraw($project, $reason, $seed);
    }

    /**
     * Selection is reproducible from the stored seed: rows are ordered by a seeded hash of their id.
     */
    private function performDraw(Project $project, ?string $reason, ?int $seed): SampleDraw
    {
        return DB::transaction(function () use ($project, $reason, $seed) {
            $fresh = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if (! $fresh->isFullDptMode()) {
                throw ValidationException::withMessages(['project' => 'Pengundian hanya tersedia pada mode DPT lengkap.']);
            }

            $alreadyDrawn = $fresh->SampleDraws()->exists();

            if ($reason === null && $alreadyDrawn) {
                throw ValidationException::withMessages(['project' => 'Sampel sudah diundi. Gunakan undi ulang dengan alasan.']);
            }

            if ($reason !== null && ! $alreadyDrawn) {
                throw ValidationException::withMessages(['project' => 'Sampel belum pernah diundi.']);
            }

            $respondents = app(RespondentService::class);

            if ($reason !== null && $respondents->hasFilledRespondents($fresh)) {
                throw ValidationException::withMessages(['project' => 'Undi ulang tidak dapat dilakukan karena sudah ada responden yang mengisi survei.']);
            }

            $params = $this->parameters($fresh);
            $population = $this->population($fresh);
            $calculation = $this->calculate($population, $params);

            if ($calculation === null) {
                throw ValidationException::withMessages(['project' => 'DPT masih kosong. Impor atau tambah data DPT terlebih dahulu.']);
            }

            $seed ??= random_int(1, 2147483647);
            $selectedIds = $this->selectIds($fresh, $calculation, $seed);

            $fresh->Dpt()->update(['is_selected' => false]);
            foreach (array_chunk($selectedIds, 500) as $chunk) {
                Dpt::whereIn('id', $chunk)->update(['is_selected' => true]);
            }

            $respondents->syncFromDraw($fresh);

            $draw = $fresh->SampleDraws()->create([
                'seed' => $seed,
                'population' => $population,
                'sample_size' => $calculation['sampleSize'],
                'invitation_count' => $calculation['invitations'],
                'selected_count' => count($selectedIds),
                'parameters' => Arr::except($params, ['population']),
                'reason' => $reason,
                'drawn_at' => now(),
            ]);

            $fresh->update(['is_dpt_locked' => true, 'is_locked' => true]);
            $project->setRawAttributes($fresh->getAttributes(), true);

            return $draw;
        });
    }

    /**
     * @param  array{sampleSize: int, invitations: int, population: int}  $calculation
     * @return list<int>
     */
    private function selectIds(Project $project, array $calculation, int $seed): array
    {
        $rows = $project->Dpt()->get(['id', 'wilayah']);

        $order = fn ($ids) => $ids->sortBy(fn (int $id) => hash('xxh3', $seed.':'.$id))->values();

        if (! $project->usesWilayah()) {
            return $order($rows->pluck('id'))->take($calculation['invitations'])->all();
        }

        $quotas = collect($this->allocation($project, $calculation)['rows'])->keyBy(fn ($row) => $row['key'] ?? '');

        return $rows
            ->groupBy(fn ($row) => filled($row->wilayah) ? $row->wilayah : '')
            ->flatMap(fn ($group, $key) => $order($group->pluck('id'))->take($quotas[$key]['undangan'] ?? 0))
            ->values()
            ->all();
    }

    private function decimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
    }
}
