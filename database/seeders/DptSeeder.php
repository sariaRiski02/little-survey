<?php

namespace Database\Seeders;

use App\Models\Dpt;
use App\Models\Project;
use Illuminate\Database\Seeder;

class DptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $wilayah = ['Sukamaju', 'Mekarsari', 'Harapan Jaya'];

        Project::doesntHave('Dpt')->each(function (Project $project) use ($wilayah): void {
            $project->update(['wilayah_label' => 'Desa']);

            Dpt::factory()
                ->count(fake()->numberBetween(20, 40))
                ->for($project)
                ->state(fn () => ['wilayah' => fake()->randomElement($wilayah)])
                ->create();
        });
    }
}
