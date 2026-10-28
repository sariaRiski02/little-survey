<?php

namespace App\Models;

use Database\Factories\DptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dpt extends Model
{
    /** @use HasFactory<DptFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'nik',
        'birth_date',
        'wilayah',
        'phone',
        'gender',
        'is_selected',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_selected' => 'boolean',
        ];
    }

    public function Project()
    {
        return $this->belongsTo(Project::class);
    }
}
