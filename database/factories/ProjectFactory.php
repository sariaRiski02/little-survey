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
            'created_at' => $createdAt,
            'updated_at' => fake()->dateTimeBetween($createdAt, 'now'),
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
