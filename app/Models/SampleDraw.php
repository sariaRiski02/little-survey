<?php

namespace App\Models;

use Database\Factories\SampleDrawFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SampleDraw extends Model
{
    /** @use HasFactory<SampleDrawFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'seed',
        'population',
        'sample_size',
        'invitation_count',
        'selected_count',
        'parameters',
        'reason',
        'drawn_at',
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'population' => 'integer',
            'sample_size' => 'integer',
            'invitation_count' => 'integer',
            'selected_count' => 'integer',
            'parameters' => 'array',
            'drawn_at' => 'datetime',
        ];
    }

    public function Project()
    {
        return $this->belongsTo(Project::class);
    }

    public function isRedraw(): bool
    {
        return filled($this->reason);
    }
}
