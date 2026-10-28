<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-8 months', '-1 week');

        return [
            'name' => fake()->randomElement(['Survei', 'Pilkades', 'Pilkada', 'Survei Preferensi']).' '.fake()->unique()->city(),
            'description' => fake()->sentence(12),
            'code_project' => 'PRJ-'.strtoupper(fake()->unique()->bothify('????##')),
            'is_locked' => false,
            'randomize_candidates' => false,
            'is_dpt_locked' => false,
            'is_questions_locked' => false,
            'is_random_sample' => true,
            'wilayah_label' => null,
            'data_mode' => Project::MODE_FULL_DPT,
            'sample_confidence' => 95,
            'sample_margin_error' => 5,
            'sample_proportion' => 0.5,
            'sample_reserve' => 20,
            'sample_population' => null,
            'created_at' => $createdAt,
            'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
        ];
    }

    public function respondentsOnly(?int $population = null): static
    {
        return $this->state(fn () => [
            'data_mode' => Project::MODE_RESPONDENTS_ONLY,
            'sample_population' => $population,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['results_published_at' => now()]);
    }

    public function questionsLocked(): static
    {
        return $this->state(fn () => ['is_questions_locked' => true]);
    }

    public function dptLocked(): static
    {
        return $this->state(fn () => ['is_dpt_locked' => true]);
    }

    public function withWilayah(string $label = 'Desa'): static
    {
        return $this->state(fn () => ['wilayah_label' => $label]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
