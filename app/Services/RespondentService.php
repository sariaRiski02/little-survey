<?php

namespace App\Services;

use App\Models\Dpt;
use App\Models\Project;
use App\Models\Respondent;
use App\Services\Concerns\ImportsSpreadsheets;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class RespondentService
{
    use ImportsSpreadsheets;

    public const DEFAULT_TEMPLATE = 'Halo {nama}, Anda diundi mengisi survei singkat. Isi di sini: {tautan}';

    private const REQUIRED_IMPORT_FIELDS = ['name', 'nik', 'birth_date', 'gender'];

    private const FIELD_ALIASES = [
        'name' => ['nama', 'name', 'namalengkap'],
        'nik' => ['nik', 'noktp', 'nomorktp'],
        'birth_date' => ['tanggallahir', 'tgllahir', 'lahir', 'birthdate', 'dob'],
        'gender' => ['jeniskelamin', 'jk', 'gender', 'kelamin'],
        'social_media' => ['sosialmedia', 'sosmed', 'mediasosial', 'instagram', 'socialmedia'],
        'phone' => ['nomortelepon', 'telepon', 'telp', 'nomorwhatsapp', 'whatsapp', 'wa', 'nowa', 'hp', 'phone'],
    ];

    public function __construct(private DptService $dptService) {}

    /**
     * @return array<string, string> field name => label
     */
    public function importFields(Project $project): array
    {
        $fields = [
            'name' => 'Nama',
            'nik' => 'NIK',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
        ];

        if ($project->usesWilayah()) {
            $fields['wilayah'] = $project->wilayah_label;
        }

        return $fields + ['social_media' => 'Sosial media', 'phone' => 'Nomor telepon'];
    }

    /**
     * @return array{total: int, filled: int, notFilled: int, notSent: int}
     */
    public function summary(Project $project): array
    {
        $row = $project->Respondent()
            ->selectRaw('count(*) as total, count(filled_at) as filled, sum(case when link_sent_at is null then 1 else 0 end) as not_sent')
            ->toBase()
            ->first();

        $total = (int) $row->total;
        $filled = (int) $row->filled;

        return [
            'total' => $total,
            'filled' => $filled,
            'notFilled' => $total - $filled,
            'notSent' => (int) $row->not_sent,
        ];
    }

    public function paginate(Project $project, ?string $search = null, ?string $wilayah = null, ?string $status = null, int $perPage = 10): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return $this->filtered($project, $wilayah, $status)
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

    /**
     * @return HasMany<Respondent, Project>
     */
    private function filtered(Project $project, ?string $wilayah, ?string $status): HasMany
    {
        return $project->Respondent()
            ->when(filled($wilayah), fn ($query) => $query->where('wilayah', $wilayah))
            ->when($status === Respondent::STATUS_NOT_SENT, fn ($query) => $query->whereNull('filled_at')->whereNull('link_sent_at'))
            ->when($status === Respondent::STATUS_SENT, fn ($query) => $query->whereNull('filled_at')->whereNotNull('link_sent_at'))
            ->when($status === Respondent::STATUS_FILLED, fn ($query) => $query->whereNotNull('filled_at'));
    }

    /**
     * @return list<string>
     */
    public function wilayahOptions(Project $project): array
    {
        return $project->Respondent()
            ->whereNotNull('wilayah')
            ->distinct()
            ->orderBy('wilayah')
            ->pluck('wilayah')
            ->all();
    }

    public function invitationTemplate(Project $project): string
    {
        return filled($project->invitation_template) ? $project->invitation_template : self::DEFAULT_TEMPLATE;
    }

    public function saveInvitationTemplate(Project $project, string $template): Project
    {
        $data = ValidatorFacade::make(['template' => trim($template)], [
            'template' => ['required', 'string', 'max:1000', 'regex:/\{tautan\}/'],
        ], [
            'template.required' => 'Isi pesan wajib diisi.',
            'template.max' => 'Pesan maksimal 1.000 karakter.',
            'template.regex' => 'Pesan harus memuat penanda {tautan}.',
        ])->validate();

        $project->update(['invitation_template' => $data['template']]);

        return $project;
    }

    public function invitationMessage(Project $project, Respondent $respondent): string
    {
        return strtr($this->invitationTemplate($project), [
            '{nama}' => $respondent->name,
            '{tautan}' => $respondent->surveyUrl(),
        ]);
    }

    public function whatsappUrl(Project $project, Respondent $respondent): ?string
    {
        $number = $respondent->whatsappNumber();

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($this->invitationMessage($project, $respondent)) : null;
    }

    public function validator(Project $project, array $data, bool $checkNikUnique = true): Validator
    {
        $nikRules = ['required', 'digits:16'];

        if ($checkNikUnique) {
            $nikRules[] = Rule::unique('respondents', 'nik')->where('project_id', $project->id);
        }

        return ValidatorFacade::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'nik' => $nikRules,
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'wilayah' => [$project->usesWilayah() ? 'required' : 'nullable', 'string', 'max:255'],
            'social_media' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{8,20}$/'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK sudah terdaftar sebagai responden.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin harus Laki-laki atau Perempuan.',
            'wilayah.required' => ($project->wilayah_label ?: 'Wilayah').' wajib diisi.',
            'wilayah.max' => 'Teks terlalu panjang (maksimal 255 karakter).',
            'social_media.max' => 'Sosial media maksimal 255 karakter.',
            'phone.regex' => 'Nomor telepon tidak valid.',
        ]);
    }

    /**
     * Add a respondent by hand. Only for "responden saja" mode.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Project $project, array $data): Respondent
    {
        $this->ensureManualMode($project);

        $validated = $this->validator($project, $data)->validate();

        return DB::transaction(fn () => $project->Respondent()->create($this->attributes($validated) + [
            'number' => $this->nextNumber($project),
            'token' => $this->newToken(),
        ]));
    }

    public function find(Project $project, int|string $id): Respondent
    {
        return $project->Respondent()->findOrFail($id);
    }

    /**
     * Record that the link was sent. The first sent link locks the candidates and the questions.
     */
    public function markSent(Project $project, int|string $id): Respondent
    {
        $respondent = $this->find($project, $id);

        DB::transaction(function () use ($project, $respondent) {
            if ($respondent->link_sent_at === null) {
                $respondent->update(['link_sent_at' => now()]);
            }

            $project->update(['is_locked' => true, 'is_questions_locked' => true]);
        });

        return $respondent;
    }

    /**
     * Issue a new token. The old link stops working and the link counts as unsent again.
     */
    public function regenerateLink(Project $project, int|string $id): Respondent
    {
        $respondent = $this->find($project, $id);

        $this->ensureNotFilled($respondent);

        $respondent->update(['token' => $this->newToken(), 'link_sent_at' => null]);

        return $respondent;
    }

    public function delete(Project $project, int|string $id): void
    {
        $respondent = $this->find($project, $id);

        $this->ensureNotFilled($respondent);

        DB::transaction(function () use ($project, $respondent) {
            if ($respondent->dpt_id) {
                Dpt::where('project_id', $project->id)->whereKey($respondent->dpt_id)->update(['is_selected' => false]);
            }

            $respondent->delete();
        });
    }

    /**
     * Replace the respondents with the DPT rows selected by the draw.
     *
     * @return int number of respondents created
     */
    public function syncFromDraw(Project $project): int
    {
        $project->Respondent()->delete();

        $now = now();
        $number = 0;
        $created = 0;

        $project->Dpt()->where('is_selected', true)->orderBy('id')->chunkById(500, function ($rows) use ($project, $now, &$number, &$created) {
            $payload = [];

            foreach ($rows as $dpt) {
                $payload[] = [
                    'project_id' => $project->id,
                    'dpt_id' => $dpt->id,
                    'number' => ++$number,
                    'token' => $this->newToken(),
                    'name' => $dpt->name,
                    'nik' => $dpt->nik,
                    'birth_date' => $dpt->birth_date->format('Y-m-d'),
                    'gender' => $dpt->gender,
                    'wilayah' => $dpt->wilayah,
                    'phone' => $dpt->phone,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Respondent::insert($payload);
            $created += count($payload);
        });

        return $created;
    }

    public function hasFilledRespondents(Project $project): bool
    {
        return $project->Respondent()->whereNotNull('filled_at')->exists();
    }

    /**
     * Guess which import field each Excel column holds, by header name.
     *
     * @param  list<string>  $headers
     * @return array<int, string> column index => field name ('' = ignored)
     */
    public function guessMapping(Project $project, array $headers): array
    {
        $aliases = self::FIELD_ALIASES;
        $aliases['wilayah'] = array_filter(['wilayah', 'desa', 'kelurahan', 'kecamatan', $this->normalizeHeader((string) $project->wilayah_label)]);
        $allowed = array_keys($this->importFields($project));
        $used = [];
        $mapping = [];

        foreach ($headers as $index => $header) {
            $mapping[$index] = '';
            $normalized = $this->normalizeHeader($header);

            foreach ($aliases as $field => $names) {
                if (in_array($field, $allowed, true) && ! in_array($field, $used, true) && in_array($normalized, $names, true)) {
                    $mapping[$index] = $field;
                    $used[] = $field;

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param  list<array{number: int, values: list<mixed>}>  $rows
     * @param  array<int, string>  $mapping
     * @return array{
     *     missingFields: list<string>,
     *     preview: list<array<string, mixed>>,
     *     valid: list<array<string, mixed>>,
     *     problems: list<array{row: int, name: string, reason: string, tone: string}>
     * }
     */
    public function analyzeRows(Project $project, array $rows, array $mapping): array
    {
        $fields = $this->importFields($project);
        $required = array_values(array_intersect(
            array_keys($fields),
            [...self::REQUIRED_IMPORT_FIELDS, ...($project->usesWilayah() ? ['wilayah'] : [])],
        ));
        $missing = array_values(array_map(
            fn ($field) => $fields[$field],
            array_diff($required, array_intersect($mapping, array_keys($fields))),
        ));

        $records = [];
        foreach ($rows as $row) {
            $records[] = ['row' => $row['number']] + $this->mapRow($row['values'], $mapping, $fields);
        }

        $result = ['missingFields' => $missing, 'preview' => array_slice($records, 0, 5), 'valid' => [], 'problems' => []];

        if ($missing !== []) {
            return $result;
        }

        $existingNiks = array_flip($project->Respondent()->pluck('nik')->all());
        $seenNiks = [];

        foreach ($records as $record) {
            $number = $record['row'];
            $data = Arr::except($record, ['row']);
            $name = $record['name'] ?? '';

            $validator = $this->validator($project, $data, false);

            if ($validator->fails()) {
                $result['problems'][] = ['row' => $number, 'name' => $name, 'reason' => $validator->errors()->first(), 'tone' => 'danger'];

                continue;
            }

            if (isset($existingNiks[$data['nik']])) {
                $result['problems'][] = ['row' => $number, 'name' => $name, 'reason' => 'NIK sudah terdaftar sebagai responden.', 'tone' => 'warning'];

                continue;
            }

            if (isset($seenNiks[$data['nik']])) {
                $result['problems'][] = ['row' => $number, 'name' => $name, 'reason' => 'NIK duplikat dengan baris '.$seenNiks[$data['nik']].'.', 'tone' => 'warning'];

                continue;
            }

            $seenNiks[$data['nik']] = $number;
            $result['valid'][] = $data;
        }

        return $result;
    }

    /**
     * Insert every valid spreadsheet row. Rows are re-validated server-side.
     *
     * @param  list<array{number: int, values: list<mixed>}>  $rows
     * @param  array<int, string>  $mapping
     * @return int number of imported rows
     */
    public function import(Project $project, array $rows, array $mapping): int
    {
        $this->ensureManualMode($project);

        $analysis = $this->analyzeRows($project, $rows, $mapping);

        if ($analysis['missingFields'] !== []) {
            throw ValidationException::withMessages([
                'mapping' => 'Kolom wajib belum dipetakan: '.implode(', ', $analysis['missingFields']).'.',
            ]);
        }

        return DB::transaction(function () use ($project, $analysis) {
            $now = now();
            $number = $this->nextNumber($project) - 1;

            $payload = [];

            foreach ($analysis['valid'] as $data) {
                $payload[] = $this->attributes($data) + [
                    'project_id' => $project->id,
                    'number' => ++$number,
                    'token' => $this->newToken(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payload, 500) as $chunk) {
                Respondent::insert($chunk);
            }

            return count($payload);
        });
    }

    public function writeTemplate(Project $project, string $path): void
    {
        $this->writeTemplateFile($path, $this->importFields($project), [
            'name' => 'Budi Santoso',
            'nik' => '7171011505900001',
            'birth_date' => '15/05/1990',
            'gender' => 'Laki-laki',
            'wilayah' => 'Contoh',
            'social_media' => '@budi',
            'phone' => '081234567890',
        ]);
    }

    /**
     * Rows for the link export, without answers. Cells are neutralised against spreadsheet formula injection.
     * The wilayah column is only included when the project uses wilayah.
     *
     * @return iterable<int, list<string>>
     */
    public function exportRows(Project $project, ?string $wilayah = null, ?string $status = null): iterable
    {
        $usesWilayah = $project->usesWilayah();

        yield array_values(array_filter(
            ['Kode', 'Nama', $usesWilayah ? $project->wilayah_label : null, 'Nomor telepon', 'Status', 'Tautan'],
            fn ($heading) => $heading !== null,
        ));

        foreach ($this->filtered($project, $wilayah, $status)->orderBy('number')->lazyById(500) as $respondent) {
            yield array_map($this->csvSafe(...), array_values(array_filter([
                $respondent->code(),
                $respondent->name,
                $usesWilayah ? (string) $respondent->wilayah : null,
                (string) $respondent->phone,
                $respondent->status(),
                $respondent->surveyUrl(),
            ], fn ($cell) => $cell !== null)));
        }
    }

    public function ensureManualMode(Project $project): void
    {
        if ($project->isFullDptMode()) {
            throw ValidationException::withMessages([
                'project' => 'Pada mode DPT lengkap, responden berasal dari undian di halaman Statistik.',
            ]);
        }
    }

    private function ensureNotFilled(Respondent $respondent): void
    {
        if ($respondent->filled_at !== null) {
            throw ValidationException::withMessages([
                'project' => 'Responden sudah mengisi survei, sehingga tidak dapat diubah atau dihapus.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'nik' => $data['nik'],
            'birth_date' => $data['birth_date'],
            'gender' => $this->nullIfBlank($data['gender'] ?? null),
            'wilayah' => $this->nullIfBlank($data['wilayah'] ?? null),
            'social_media' => $this->nullIfBlank($data['social_media'] ?? null),
            'phone' => $this->nullIfBlank($data['phone'] ?? null),
        ];
    }

    private function nextNumber(Project $project): int
    {
        return (int) $project->Respondent()->max('number') + 1;
    }

    private function newToken(): string
    {
        return Str::random(40);
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
