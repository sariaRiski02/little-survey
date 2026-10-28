<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    public const TYPE_SINGLE = 'tunggal';

    public const TYPE_MULTIPLE = 'ganda';

    public const TYPE_SCALE = 'skala';

    public const TYPE_TEXT = 'isian';

    public const TYPE_CANDIDATE = 'kandidat';

    public const TYPES = [
        self::TYPE_SINGLE => 'Pilihan tunggal',
        self::TYPE_MULTIPLE => 'Pilihan ganda',
        self::TYPE_SCALE => 'Skala',
        self::TYPE_TEXT => 'Isian bebas',
        self::TYPE_CANDIDATE => 'Pilih kandidat',
    ];

    /** Types whose answer choices are typed in by the admin. */
    public const OPTION_TYPES = [self::TYPE_SINGLE, self::TYPE_MULTIPLE, self::TYPE_SCALE];

    protected $fillable = [
        'project_id',
        'position',
        'text',
        'description',
        'type',
        'options',
        'is_required',
        'has_refuse_option',
        'branch_question_id',
        'branch_answer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Question $question) {
            $question->position ??= (int) static::where('project_id', $question->project_id)->max('position') + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'options' => 'array',
            'is_required' => 'boolean',
            'has_refuse_option' => 'boolean',
        ];
    }

    public function Project()
    {
        return $this->belongsTo(Project::class);
    }

    public function BranchQuestion()
    {
        return $this->belongsTo(self::class, 'branch_question_id');
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::OPTION_TYPES, true);
    }
}
