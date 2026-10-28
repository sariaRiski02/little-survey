<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Respondent;
use App\Models\RespondentAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RespondentAnswer>
 */
class RespondentAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'respondent_id' => Respondent::factory(),
            'question_id' => Question::factory(),
            'values' => ['Ya'],
            'is_refused' => false,
        ];
    }
}
