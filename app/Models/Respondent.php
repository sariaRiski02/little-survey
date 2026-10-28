<?php

namespace App\Models;

use Database\Factories\RespondentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Respondent extends Model
{
    /** @use HasFactory<RespondentFactory> */
    use HasFactory;

    public const STATUS_NOT_SENT = 'belum';

    public const STATUS_SENT = 'terkirim';

    public const STATUS_FILLED = 'mengisi';

    protected $fillable = [
        'project_id',
        'dpt_id',
        'number',
        'token',
        'name',
        'nik',
        'birth_date',
        'gender',
        'wilayah',
        'social_media',
        'phone',
        'link_sent_at',
        'filled_at',
    ];

    protected $hidden = ['token'];

    protected static function booted(): void
    {
        static::creating(function (Respondent $respondent) {
            $respondent->number ??= (int) static::where('project_id', $respondent->project_id)->max('number') + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'birth_date' => 'date',
            'link_sent_at' => 'datetime',
            'filled_at' => 'datetime',
        ];
    }

    public function Project()
    {
        return $this->belongsTo(Project::class);
    }

    public function Answer()
    {
        return $this->hasMany(RespondentAnswer::class);
    }

    public function Dpt()
    {
        return $this->belongsTo(Dpt::class);
    }

    public function code(): string
    {
        return 'R-'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
    }

    public function status(): string
    {
        return match (true) {
            $this->filled_at !== null => self::STATUS_FILLED,
            $this->link_sent_at !== null => self::STATUS_SENT,
            default => self::STATUS_NOT_SENT,
        };
    }

    public function surveyUrl(): string
    {
        return route('survei', ['token' => $this->token]);
    }

    /**
     * Phone number in international format without symbols, as wa.me expects (08xx becomes 628xx).
     */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}
