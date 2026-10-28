<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = [
            ['name' => 'Survei Preferensi Pemilih Desa Makmur', 'code_project' => 'PRJ-MKM001'],
            ['name' => 'Pilkades Sukamaju 2026', 'code_project' => 'PRJ-SKM002'],
            ['name' => 'Survei Pilkada Kota Tomohon', 'code_project' => 'PRJ-TMH003', 'is_locked' => true],
            ['name' => 'Uji Coba Kuesioner', 'code_project' => 'PRJ-UJC004'],
            ['name' => 'Survei Caleg DPRD Dapil 2', 'code_project' => 'PRJ-DPR005', 'is_locked' => true],
            ['name' => 'Survei Pilgub Sulawesi Utara', 'code_project' => 'PRJ-SLT006'],
        ];

        foreach ($projects as $project) {
            Project::factory()->create($project);
        }
    }
}
