<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Dpt;
use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use App\Services\QuestionService;
use App\Services\RespondentService;
use App\Services\StatistikService;
use App\Services\SurveyService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A realistic fieldwork snapshot: Desa Kema 3, 14 jaga, 3.700 voters in the DPT, two candidates
 * without a ballot number (told apart by colour), the sample drawn and about 70% of it answered.
 * Results are not published yet. Re-running replaces the project.
 */
class DesaKema3Seeder extends Seeder
{
    private const CODE = 'PRJ-KEMA03';

    private const TOTAL_VOTERS = 3700;

    private const DRAW_SEED = 20261028;

    /** Relative size of each jaga; scaled so the DPT adds up to exactly 3.700. */
    private const JAGA_WEIGHTS = [1.00, 0.90, 0.75, 0.85, 0.70, 1.10, 0.90, 0.60, 0.80, 1.00, 0.85, 0.70, 0.95, 0.90];

    /** Share of each jaga that prefers the first candidate. */
    private const FIRST_CANDIDATE_SHARE = [0.62, 0.58, 0.45, 0.66, 0.40, 0.52, 0.48, 0.70, 0.36, 0.55, 0.60, 0.44, 0.50, 0.57];

    private const MALE_NAMES = ['Stefanus', 'Jefry', 'Ferdy', 'Marlon', 'Olvie', 'Rendy', 'Johanis', 'Yohanes', 'Alfrets', 'Hendrik', 'Roy', 'Daniel', 'Joni', 'Max', 'Frangky', 'Steven', 'Rony', 'Jemmy', 'Anton', 'Herman', 'Benny', 'Recky', 'Julius', 'Arnold', 'Vicky'];

    private const FEMALE_NAMES = ['Meity', 'Vera', 'Yanti', 'Rensy', 'Merry', 'Sonya', 'Novita', 'Debby', 'Lientje', 'Fenny', 'Grace', 'Ineke', 'Maria', 'Selvi', 'Jeane', 'Ivonne', 'Cindy', 'Femmy', 'Santi', 'Tineke', 'Wulan', 'Ratna', 'Lidya', 'Sherly', 'Rosita'];

    private const SURNAMES = ['Lumempouw', 'Sumampouw', 'Mamahit', 'Rumajar', 'Kalalo', 'Lengkong', 'Tumewu', 'Wowor', 'Pangemanan', 'Walangitan', 'Rondonuwu', 'Lumanauw', 'Kaunang', 'Mandagi', 'Watung', 'Sondakh', 'Tampi', 'Pontoh', 'Mokodompit', 'Tangkudung', 'Kumaat', 'Ratulangi', 'Polii', 'Sambuaga', 'Takaendengan', 'Sompie', 'Runtuwene', 'Lolowang'];

    private const OPEN_ANSWERS = [
        'Perbaikan jalan antar jaga',
        'Air bersih supaya lancar',
        'Lapangan kerja untuk anak muda',
        'Bantuan untuk petani kelapa',
        'Lampu jalan di setiap jaga',
        'Harga kopra dan cengkih yang stabil',
        'Perbaikan drainase supaya tidak banjir',
        'Pembangunan dermaga untuk nelayan',
        'Pelayanan kantor desa yang lebih cepat',
        'Bantuan bibit dan pupuk',
        'Perhatian untuk lansia dan disabilitas',
        'Kegiatan olahraga dan pemuda',
    ];

    public function run(): void
    {
        mt_srand(3700);

        Project::where('code_project', self::CODE)->delete();

        $project = Project::create([
            'name' => 'Survei Pemilihan Hukum Tua Desa Kema 3',
            'description' => 'Survei preferensi pemilih pada pemilihan Hukum Tua Desa Kema 3, Kecamatan Kema.',
            'code_project' => self::CODE,
            'wilayah_label' => 'Jaga',
            'data_mode' => Project::MODE_FULL_DPT,
            'randomize_candidates' => true,
            'is_random_sample' => true,
        ]);

        $this->seedCandidates($project);
        $this->seedDpt($project);
        app(QuestionService::class)->applyTemplate($project);

        $draw = app(StatistikService::class)->draw($project, self::DRAW_SEED);
        $this->simulateFieldwork($project);

        $this->command?->info(sprintf(
            '%s: %d DPT di %d jaga, %d kandidat, sampel %d (undangan %d), %d responden sudah mengisi.',
            $project->name,
            $project->Dpt()->count(),
            count(self::JAGA_WEIGHTS),
            $project->Candidate()->count(),
            $draw->sample_size,
            $draw->selected_count,
            $project->Respondent()->whereNotNull('filled_at')->count(),
        ));
        $this->command?->info('Buka /'.self::CODE.'/dashboard. Hasil belum dipublikasikan; aktifkan di Pengaturan untuk melihat /hasil/'.self::CODE.'.');
    }

    private function seedCandidates(Project $project): void
    {
        foreach ([['Jefry Rumajar', '#0F6B6B'], ['Stenly Walangitan', '#D97706']] as [$name, $color]) {
            Candidate::create([
                'project_id' => $project->id,
                'name' => strtolower($name),
                'number' => null,
                'color' => $color,
                'picture' => null,
            ]);
        }
    }

    private function seedDpt(Project $project): void
    {
        $sizes = $this->jagaSizes();
        $now = now();
        $sequence = 0;
        $rows = [];

        foreach ($sizes as $index => $size) {
            $jaga = 'Jaga '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            for ($i = 0; $i < $size; $i++) {
                $female = mt_rand(0, 1) === 1;
                $birth = $this->birthDate();
                $sequence++;

                $rows[] = [
                    'project_id' => $project->id,
                    'name' => ($female ? self::FEMALE_NAMES : self::MALE_NAMES)[mt_rand(0, 24)].' '.self::SURNAMES[mt_rand(0, count(self::SURNAMES) - 1)],
                    'nik' => '710604'
                        .str_pad((string) ((int) $birth->format('d') + ($female ? 40 : 0)), 2, '0', STR_PAD_LEFT)
                        .$birth->format('my')
                        .str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                    'birth_date' => $birth->format('Y-m-d'),
                    'wilayah' => $jaga,
                    'phone' => mt_rand(1, 100) <= 62 ? $this->phone() : null,
                    'gender' => $female ? 'Perempuan' : 'Laki-laki',
                    'is_selected' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                Dpt::insert($chunk);
            }
        });
    }

    /**
     * @return list<int>
     */
    private function jagaSizes(): array
    {
        $total = array_sum(self::JAGA_WEIGHTS);
        $exact = array_map(fn (float $weight) => self::TOTAL_VOTERS * $weight / $total, self::JAGA_WEIGHTS);
        $sizes = array_map('intval', $exact);

        $remainders = array_map(fn (float $value, int $size) => $value - $size, $exact, $sizes);
        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, self::TOTAL_VOTERS - array_sum($sizes)) as $index) {
            $sizes[$index]++;
        }

        return $sizes;
    }

    /**
     * Adults only (17+ on the survey date), skewed towards working age.
     */
    private function birthDate(): Carbon
    {
        $age = (int) round(17 + (mt_rand(0, 1000) / 1000) ** 1.25 * 63);

        return Carbon::create(2026, 10, 28)->subYears($age)->subDays(mt_rand(0, 364));
    }

    private function phone(): string
    {
        $prefix = ['0812', '0813', '0821', '0852', '0853', '0811', '0895'][mt_rand(0, 6)];

        return $prefix.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Links go out to almost everyone, then roughly 70% of the sample answers, with each jaga leaning
     * towards one candidate a little differently so the significance tests have something to find.
     */
    private function simulateFieldwork(Project $project): void
    {
        $questions = $project->Question()->get()->values();
        [$intention, $candidateQuestion, $confidence, $open] = [$questions[0], $questions[1], $questions[2], $questions[3]];
        $survey = app(SurveyService::class);
        $candidates = $project->Candidate()->orderBy('id')->get()->map(fn (Candidate $candidate) => ucwords($candidate->name))->all();

        $respondents = $project->Respondent()->orderBy('number')->get();
        $now = now();

        $sent = $respondents->filter(fn () => mt_rand(1, 100) <= 97);
        foreach ($sent as $respondent) {
            $sentAt = $now->copy()->subDays(mt_rand(1, 6))->subMinutes(mt_rand(0, 1400));
            Respondent::whereKey($respondent->id)->update(['link_sent_at' => $sentAt]);
            $respondent->link_sent_at = $sentAt;
        }

        // The first sent link locks the candidates and the questions, as in real use.
        if ($sent->isNotEmpty()) {
            app(RespondentService::class)->markSent($project, $sent->first()->id);
        }

        foreach ($sent->filter(fn () => mt_rand(1, 100) <= 72) as $respondent) {
            $respondent = Respondent::with('Project')->find($respondent->id);
            $jaga = (int) substr($respondent->wilayah, -2) - 1;

            $survey->submit($respondent, $this->answersFor($intention, $candidateQuestion, $confidence, $open, $candidates, $jaga));

            $filledAt = min($now, $respondent->link_sent_at->copy()->addMinutes(mt_rand(10, 2400)));
            Respondent::whereKey($respondent->id)->update(['filled_at' => $filledAt]);
        }
    }

    /**
     * @param  list<string>  $candidates
     * @return array<int, string|list<string>>
     */
    private function answersFor(Question $intention, Question $candidateQuestion, Question $confidence, Question $open, array $candidates, int $jaga): array
    {
        $answers = [$intention->id => $this->weighted(['Ya' => 88, 'Tidak' => 4, 'Belum tahu' => 8])];

        if ($answers[$intention->id] === 'Ya') {
            $roll = mt_rand(1, 1000) / 10;
            $answers[$candidateQuestion->id] = match (true) {
                $roll <= 6 => SurveyService::REFUSE,
                $roll <= 6 + 94 * self::FIRST_CANDIDATE_SHARE[$jaga] => $candidates[0],
                default => $candidates[1],
            };
        }

        $answers[$confidence->id] = $this->weighted(array_combine($confidence->options, [2, 6, 22, 40, 30]));

        if (mt_rand(1, 100) <= 35) {
            $answers[$open->id] = self::OPEN_ANSWERS[mt_rand(0, count(self::OPEN_ANSWERS) - 1)];
        }

        return $answers;
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $pick = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $pick -= $weight;

            if ($pick <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }
}
