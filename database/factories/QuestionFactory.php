<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
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
            'text' => rtrim(fake()->sentence(8), '.').'?',
            'description' => null,
            'type' => Question::TYPE_SINGLE,
            'options' => ['Ya', 'Tidak'],
            'is_required' => true,
            'has_refuse_option' => false,
            'branch_question_id' => null,
            'branch_answer' => null,
        ];
    }
}
