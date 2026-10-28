<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const MODE_FULL_DPT = 'dpt_lengkap';

    public const MODE_RESPONDENTS_ONLY = 'responden_saja';

    protected $fillable = [
        'name',
        'description',
        'code_project',
        'is_locked',
        'is_dpt_locked',
        'is_questions_locked',
        'wilayah_label',
        'data_mode',
        'sample_confidence',
        'sample_margin_error',
        'sample_proportion',
        'sample_reserve',
        'sample_population',
        'invitation_template',
        'is_random_sample',
        'results_published_at',
        'randomize_candidates',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_dpt_locked' => 'boolean',
            'is_questions_locked' => 'boolean',
            'is_random_sample' => 'boolean',
            'results_published_at' => 'datetime',
            'sample_confidence' => 'integer',
            'sample_margin_error' => 'float',
            'sample_proportion' => 'float',
            'sample_reserve' => 'integer',
            'sample_population' => 'integer',
            'randomize_candidates' => 'boolean',
        ];
    }

    public function Candidate()
    {
        return $this->hasMany(Candidate::class);
    }

    public function Dpt()
    {
        return $this->hasMany(Dpt::class);
    }

    public function Question()
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    public function Respondent()
    {
        return $this->hasMany(Respondent::class);
    }

    public function SampleDraws()
    {
        return $this->hasMany(SampleDraw::class);
    }

    public function latestSampleDraw()
    {
        return $this->hasOne(SampleDraw::class)->latestOfMany();
    }

    public function resultsArePublished(): bool
    {
        return $this->results_published_at !== null;
    }

    public function isFullDptMode(): bool
    {
        return ($this->data_mode ?? self::MODE_FULL_DPT) === self::MODE_FULL_DPT;
    }

    public function usesWilayah(): bool
    {
        return filled($this->wilayah_label);
    }
}
