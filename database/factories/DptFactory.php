<?php

namespace Database\Factories;

use App\Models\Dpt;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dpt>
 */
class DptFactory extends Factory
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
        $day = (int) $birthDate->format('d') + ($isFemale ? 40 : 0);
        $nik = fake()->numerify('7171##')
            .str_pad((string) $day, 2, '0', STR_PAD_LEFT)
            .$birthDate->format('my')
            .fake()->numerify('####');

        return [
            'project_id' => Project::factory(),
            'name' => fake()->name($isFemale ? 'female' : 'male'),
            'nik' => $nik,
            'birth_date' => $birthDate->format('Y-m-d'),
            'wilayah' => null,
            'phone' => null,
            'gender' => $isFemale ? 'Perempuan' : 'Laki-laki',
            'is_selected' => false,
        ];
    }

    public function selected(): static
    {
        return $this->state(fn () => ['is_selected' => true]);
    }
}
