<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'number',
        'color',
        'picture',
    ];

    public function Project()
    {
        return $this->belongsTo(Project::class);
    }
}
