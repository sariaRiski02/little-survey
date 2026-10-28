<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->randomElement(['Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gunawan', 'Herlina', 'Indra', 'Joko'])
                .' '.fake()->randomElement(['Santoso', 'Rahardjo', 'Lestari', 'Wijaya', 'Pratama', 'Sari', 'Kusuma', 'Hidayat']),
            'number' => fake()->numberBetween(1, 10),
            'color' => fake()->randomElement(['#0F6B6B', '#D97706', '#7C3AED', '#DC2626', '#2563EB', '#16A34A']),
            'picture' => 'candidates/placeholder.png',
        ];
    }
}
