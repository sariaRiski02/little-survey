<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectSettingService
{
    /**
     * The data mode can only change on a clean slate: no DPT, no respondents, no draw.
     */
    public function canChangeMode(Project $project): bool
    {
        return ! $project->Dpt()->exists()
            && ! $project->Respondent()->exists()
            && ! $project->SampleDraws()->exists();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeInput(array $input): array
    {
        foreach (['name', 'wilayah_label'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }

        if (($input['population'] ?? null) === '') {
            $input['population'] = null;
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Project $project, array $input): Project
    {
        $input = $this->normalizeInput($input);

        $data = ValidatorFacade::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'wilayah_label' => ['nullable', 'string', 'max:50'],
            'data_mode' => ['required', Rule::in([Project::MODE_FULL_DPT, Project::MODE_RESPONDENTS_ONLY])],
            'population' => ['nullable', 'integer', 'min:1', 'max:2000000000'],
            'is_random_sample' => ['boolean'],
            'publish_results' => ['boolean'],
        ], [
            'name.required' => 'Nama proyek wajib diisi.',
            'name.max' => 'Nama proyek maksimal 255 karakter.',
            'wilayah_label.max' => 'Sebutan wilayah maksimal 50 karakter.',
            'data_mode.required' => 'Mode data wajib dipilih.',
            'data_mode.in' => 'Mode data tidak valid.',
            'population.integer' => 'Jumlah DPT harus berupa bilangan bulat.',
            'population.min' => 'Jumlah DPT minimal 1.',
            'population.max' => 'Jumlah DPT terlalu besar.',
        ])->validate();

        $modeChanged = $data['data_mode'] !== ($project->data_mode ?? Project::MODE_FULL_DPT);

        if ($modeChanged && ! $this->canChangeMode($project)) {
            throw ValidationException::withMessages([
                'data_mode' => 'Mode data tidak dapat diganti karena sudah ada DPT, responden, atau undian.',
            ]);
        }

        $attributes = [
            'name' => $data['name'],
            'wilayah_label' => filled($data['wilayah_label'] ?? null) ? $data['wilayah_label'] : null,
            'data_mode' => $data['data_mode'],
        ];

        if ($data['data_mode'] === Project::MODE_RESPONDENTS_ONLY) {
            $attributes['sample_population'] = $data['population'] ?? null;
            $attributes['is_random_sample'] = (bool) ($data['is_random_sample'] ?? false);
        } else {
            $attributes['is_random_sample'] = true;
        }

        $publish = (bool) ($data['publish_results'] ?? false);

        if ($publish && ! $project->resultsArePublished()) {
            $attributes['results_published_at'] = now();
        } elseif (! $publish) {
            $attributes['results_published_at'] = null;
        }

        $project->update($attributes);

        return $project;
    }
}
