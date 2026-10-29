<?php

namespace App\Services;

use App\Models\Dpt;
use App\Models\Project;
use App\Services\Concerns\ImportsSpreadsheets;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use OpenSpout\Common\Entity\Row;

class DptService
{
    use ImportsSpreadsheets;

    private const REQUIRED_IMPORT_FIELDS = ['name', 'nik', 'birth_date'];

    private const FIELD_ALIASES = [
        'name' => ['nama', 'name', 'namalengkap'],
        'nik' => ['nik', 'noktp', 'nomorktp'],
        'birth_date' => ['tanggallahir', 'tgllahir', 'lahir', 'birthdate', 'dob'],
        'phone' => ['nomorwhatsapp', 'whatsapp', 'wa', 'nowa', 'telepon', 'hp', 'phone'],
        'gender' => ['jeniskelamin', 'jk', 'gender', 'kelamin'],
    ];

    /**
     * Import target fields keyed by field name, labelled for the given project.
     *
     * @return array<string, string>
     */
    public function importFields(Project $project): array
    {
        $fields = [
            'name' => 'Nama',
            'nik' => 'NIK',
            'birth_date' => 'Tanggal lahir',
        ];

        if ($project->usesWilayah()) {
            $fields['wilayah'] = $project->wilayah_label;
        }

        return $fields + [
            'phone' => 'Nomor WhatsApp',
            'gender' => 'Jenis kelamin',
        ];
    }

    public function create(Project $project, array $data): Dpt
    {
        $this->ensureUnlocked($project);

        return $project->Dpt()->create([
            'name' => trim($data['name']),
            'nik' => $data['nik'],
            'birth_date' => $data['birth_date'],
            'wilayah' => $this->nullIfBlank($data['wilayah'] ?? null),
            'phone' => $this->nullIfBlank($data['phone'] ?? null),
            'gender' => $this->nullIfBlank($data['gender'] ?? null),
        ]);
    }

    /**
     * Update the editable fields (name, birth date, wilayah) of a DPT row owned by the project.
     *
     * @param  array{name: string, birth_date: string, wilayah?: string|null}  $data
     */
    public function update(Project $project, int|string $id, array $data): Dpt
    {
        $this->ensureUnlocked($project);

        $dpt = $project->Dpt()->findOrFail($id);

        $attributes = [
            'name' => trim($data['name']),
            'birth_date' => $data['birth_date'],
        ];

        if ($project->usesWilayah()) {
            $attributes['wilayah'] = $this->nullIfBlank($data['wilayah'] ?? null);
        }

        $dpt->update($attributes);

        return $dpt;
    }

    public function delete(Project $project, int|string $id): void
    {
        $this->ensureUnlocked($project);

        $project->Dpt()->findOrFail($id)->delete();
    }

    /**
     * Delete the given DPT rows of the project. Ids from other projects are ignored.
     *
     * @param  list<int|string>  $ids
     * @return int number of deleted rows
     */
    public function deleteMany(Project $project, array $ids): int
    {
        $this->ensureUnlocked($project);

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $deleted = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            $deleted += $project->Dpt()->whereKey($chunk)->delete();
        }

        return $deleted;
    }

    /**
     * Delete every DPT row matching the list filters (search and wilayah).
     *
     * @return int number of deleted rows
     */
    public function deleteMatching(Project $project, ?string $search = null, ?string $wilayah = null): int
    {
        $this->ensureUnlocked($project);

        return $this->filtered($project, $search, $wilayah)->delete();
    }

    public function find(Project $project, int|string $id): Dpt
    {
        return $project->Dpt()->findOrFail($id);
    }

    public function paginate(Project $project, ?string $search = null, ?string $wilayah = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->filtered($project, $search, $wilayah)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @return HasMany<Dpt, Project>
     */
    private function filtered(Project $project, ?string $search, ?string $wilayah): HasMany
    {
        $search = trim((string) $search);

        return $project->Dpt()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('nik', 'like', $like));
            })
            ->when(filled($wilayah), fn ($query) => $query->where('wilayah', $wilayah));
    }

    /**
     * @return array{total: int, perWilayah: Collection<int, array{nama: string, jumlah: int}>}
     */
    public function summary(Project $project): array
    {
        $perWilayah = collect();

        if ($project->usesWilayah()) {
            $perWilayah = $project->Dpt()
                ->whereNotNull('wilayah')
                ->select('wilayah', DB::raw('count(*) as jumlah'))
                ->groupBy('wilayah')
                ->orderBy('wilayah')
                ->get()
                ->map(fn ($row) => ['nama' => $row->wilayah, 'jumlah' => (int) $row->jumlah]);
        }

        return [
            'total' => $project->Dpt()->count(),
            'perWilayah' => $perWilayah,
        ];
    }

    /**
     * @return list<string>
     */
    public function wilayahOptions(Project $project): array
    {
        return $project->Dpt()
            ->whereNotNull('wilayah')
            ->distinct()
            ->orderBy('wilayah')
            ->pluck('wilayah')
            ->all();
    }

    /**
     * Build a validator for a DPT row. Only the keys present in `$data` are validated.
     */
    public function validator(Project $project, array $data, bool $checkNikUnique = true): Validator
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'digits:16'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'wilayah' => [$project->usesWilayah() ? 'required' : 'nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'gender' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
        ];

        if ($checkNikUnique) {
            $rules['nik'][] = Rule::unique('dpts', 'nik')->where('project_id', $project->id);
        }

        return ValidatorFacade::make(
            $data,
            array_intersect_key($rules, $data),
            $this->messages($project),
        );
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
        $aliases['wilayah'] = array_filter([
            'wilayah', 'desa', 'kelurahan', 'kecamatan', 'tps', $this->normalizeHeader((string) $project->wilayah_label),
        ]);
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
     * Validate spreadsheet rows against a column mapping.
     *
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
        $mapped = array_values(array_intersect($mapping, array_keys($fields)));
        $missing = array_values(array_map(fn ($field) => $fields[$field], array_diff($required, $mapped)));

        $records = [];
        foreach ($rows as $row) {
            $records[] = ['row' => $row['number']] + $this->mapRow($row['values'], $mapping, $fields);
        }

        $result = [
            'missingFields' => $missing,
            'preview' => array_slice($records, 0, 5),
            'valid' => [],
            'problems' => [],
        ];

        if ($missing !== []) {
            return $result;
        }

        $existingNiks = array_flip($project->Dpt()->pluck('nik')->all());
        $seenNiks = [];

        foreach ($records as $record) {
            $number = $record['row'];
            $data = Arr::except($record, ['row']);
            $label = $record['name'] ?? '';

            $validator = $this->validator($project, $data, false);

            if ($validator->fails()) {
                $result['problems'][] = ['row' => $number, 'name' => $label, 'reason' => $validator->errors()->first(), 'tone' => 'danger'];

                continue;
            }

            if (isset($existingNiks[$data['nik']])) {
                $result['problems'][] = ['row' => $number, 'name' => $label, 'reason' => 'NIK sudah terdaftar di DPT.', 'tone' => 'warning'];

                continue;
            }

            if (isset($seenNiks[$data['nik']])) {
                $result['problems'][] = ['row' => $number, 'name' => $label, 'reason' => 'NIK duplikat dengan baris '.$seenNiks[$data['nik']].'.', 'tone' => 'warning'];

                continue;
            }

            $seenNiks[$data['nik']] = $number;
            $result['valid'][] = $data;
        }

        return $result;
    }

    /**
     * Insert every valid row of the spreadsheet. Rows are re-validated server-side.
     *
     * @param  list<array{number: int, values: list<mixed>}>  $rows
     * @param  array<int, string>  $mapping
     * @return int number of imported rows
     */
    public function import(Project $project, array $rows, array $mapping): int
    {
        $this->ensureUnlocked($project);

        $analysis = $this->analyzeRows($project, $rows, $mapping);

        if ($analysis['missingFields'] !== []) {
            throw ValidationException::withMessages([
                'mapping' => 'Kolom wajib belum dipetakan: '.implode(', ', $analysis['missingFields']).'.',
            ]);
        }

        $now = now();
        $payload = array_map(fn (array $data) => [
            'project_id' => $project->id,
            'name' => trim($data['name']),
            'nik' => $data['nik'],
            'birth_date' => $data['birth_date'],
            'wilayah' => $this->nullIfBlank($data['wilayah'] ?? null),
            'phone' => $this->nullIfBlank($data['phone'] ?? null),
            'gender' => $this->nullIfBlank($data['gender'] ?? null),
            'is_selected' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ], $analysis['valid']);

        DB::transaction(function () use ($payload) {
            foreach (array_chunk($payload, 500) as $chunk) {
                Dpt::insert($chunk);
            }
        });

        return count($payload);
    }

    public function writeTemplate(Project $project, string $path): void
    {
        $fields = $this->importFields($project);
        $sample = [
            'name' => 'Budi Santoso',
            'nik' => '7171011505900001',
            'birth_date' => '15/05/1990',
            'wilayah' => 'Contoh',
            'phone' => '081234567890',
            'gender' => 'Laki-laki',
        ];

        $this->writeTemplateFile($path, $fields, $sample);
    }

    /**
     * The DPT cannot be changed once it is locked.
     * The lock state is re-read from the database to avoid stale component state.
     */
    public function ensureUnlocked(Project $project): void
    {
        if (Project::whereKey($project->getKey())->value('is_dpt_locked')) {
            throw ValidationException::withMessages([
                'project' => 'DPT terkunci karena sampel sudah diundi, sehingga tidak dapat diubah.',
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function messages(Project $project): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK sudah terdaftar di DPT.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'wilayah.required' => ($project->wilayah_label ?: 'Wilayah').' wajib diisi.',
            'wilayah.max' => 'Teks terlalu panjang (maksimal 255 karakter).',
            'phone.regex' => 'Nomor WhatsApp tidak valid.',
            'gender.in' => 'Jenis kelamin harus Laki-laki atau Perempuan.',
        ];
    }
}
