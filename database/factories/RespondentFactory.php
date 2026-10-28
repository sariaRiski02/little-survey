<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Respondent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Respondent>
 */
class RespondentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $birthDate = fake()->dateTimeBetween('-70 years', '-17 years');
        $isFemale = fake()->boolean();
        $nik = fake()->numerify('7171##')
            .str_pad((string) ((int) $birthDate->format('d') + ($isFemale ? 40 : 0)), 2, '0', STR_PAD_LEFT)
            .$birthDate->format('my')
            .fake()->numerify('####');

        return [
            'project_id' => Project::factory(),
            'token' => Str::random(32),
            'name' => fake()->name($isFemale ? 'female' : 'male'),
            'nik' => $nik,
            'birth_date' => $birthDate->format('Y-m-d'),
            'gender' => $isFemale ? 'Perempuan' : 'Laki-laki',
            'wilayah' => null,
            'social_media' => null,
            'phone' => null,
            'link_sent_at' => null,
            'filled_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['link_sent_at' => now()->subDay()]);
    }

    public function filled(): static
    {
        return $this->state(fn () => ['link_sent_at' => now()->subDays(2), 'filled_at' => now()->subDay()]);
    }
}
