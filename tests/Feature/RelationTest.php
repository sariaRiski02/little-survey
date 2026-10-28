<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */

    public function test_project_has_many_candidates(): void
    {
        $project = Project::factory()->create();
        $candidates = Candidate::factory()->count(3)->for($project)->create();

        $this->assertCount(3, $project->Candidate);
        $this->assertEqualsCanonicalizing(
            $candidates->pluck('id')->all(),
            $project->Candidate->pluck('id')->all(),
        );
    }

    public function test_candidate_belongs_to_project(): void
    {
        $project = Project::factory()->create();
        $candidate = Candidate::factory()->for($project)->create();

        $this->assertTrue($candidate->Project->is($project));
        $this->assertSame($project->id, $candidate->project_id);
    }

    public function test_candidates_are_not_mixed_between_projects(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();
        Candidate::factory()->count(2)->for($projectA)->create();
        Candidate::factory()->for($projectB)->create();

        $this->assertCount(2, $projectA->Candidate);
        $this->assertCount(1, $projectB->Candidate);
    }

    public function test_candidates_are_deleted_with_their_project(): void
    {
        $project = Project::factory()->create();
        Candidate::factory()->count(2)->for($project)->create();

        $project->delete();

        $this->assertSame(0, Candidate::count());
    }
}
