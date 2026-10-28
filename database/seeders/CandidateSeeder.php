<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $candidates = [
            ['name' => 'Ahmad Santoso', 'color' => '#0F6B6B'],
            ['name' => 'Budi Rahardjo', 'color' => '#D97706'],
            ['name' => 'Citra Lestari', 'color' => '#7C3AED'],
            ['name' => 'Dewi Kusuma', 'color' => '#DC2626'],
        ];

        Project::doesntHave('Candidate')->each(function (Project $project) use ($candidates): void {
            foreach (array_slice($candidates, 0, fake()->numberBetween(2, 4)) as $index => $candidate) {
                Candidate::factory()->for($project)->create($candidate + ['number' => $index + 1]);
            }
        });
    }
}
