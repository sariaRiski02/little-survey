<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SampleDraw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleDraw>
 */
class SampleDrawFactory extends Factory
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
            'seed' => fake()->numberBetween(1, 2147483647),
            'population' => 1000,
            'sample_size' => 278,
            'invitation_count' => 348,
            'selected_count' => 348,
            'parameters' => ['confidence' => 95, 'margin_error' => 5, 'proportion' => 0.5, 'reserve' => 20],
            'reason' => null,
            'drawn_at' => now(),
        ];
    }
}
