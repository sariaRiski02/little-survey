<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds admin links without a stray project query parameter', function (string $page) {
    $project = Project::factory()->create();

    $response = $this->get(route($page, ['project' => $project->code_project]))->assertOk();
    $html = $response->getContent();

    foreach (['dashboard', 'kandidat', 'dpt', 'statistik', 'pertanyaan', 'responden', 'pengaturan'] as $route) {
        $response->assertSee('href="'.route($route, ['project' => $project->code_project]).'"', false);
    }

    expect($html)->not->toContain('project=');
})->with(['dashboard', 'kandidat', 'dpt', 'statistik', 'pertanyaan', 'responden', 'pengaturan']);

it('keeps explicit project parameters working next to the default', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    $this->get(route('dashboard', ['project' => $project->code_project]))->assertOk();

    expect(route('dashboard', ['project' => $other->code_project]))->toEndWith('/'.$other->code_project.'/dashboard');
});
