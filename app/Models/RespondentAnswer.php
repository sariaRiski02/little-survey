<?php

namespace App\Models;

use Database\Factories\RespondentAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RespondentAnswer extends Model
{
    /** @use HasFactory<RespondentAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'respondent_id',
        'question_id',
        'values',
        'is_refused',
    ];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'is_refused' => 'boolean',
        ];
    }

    public function Respondent()
    {
        return $this->belongsTo(Respondent::class);
    }

    public function Question()
    {
        return $this->belongsTo(Question::class);
    }
}
