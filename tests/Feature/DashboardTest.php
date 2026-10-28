<?php

use App\Models\Candidate;
use App\Models\Dpt;
use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use App\Models\RespondentAnswer;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function answer(Question $question, Respondent $respondent, ?string $value): void
{
    RespondentAnswer::factory()->create([
        'respondent_id' => $respondent->id,
        'question_id' => $question->id,
        'values' => $value === null ? [] : [$value],
        'is_refused' => $value === null,
    ]);
    $respondent->update(['filled_at' => now()]);
}

/**
 * @return array{0: Project, 1: Question}
 */
function dashboardFixture(array $projectState = []): array
{
    $project = Project::factory()->state($projectState)->create();
    Candidate::factory()->for($project)->create(['name' => 'ahmad', 'number' => 1, 'color' => '#111111']);
    Candidate::factory()->for($project)->create(['name' => 'budi', 'number' => 2, 'color' => '#222222']);
    $question = Question::factory()->for($project)->create(['type' => 'kandidat', 'options' => null, 'has_refuse_option' => true]);

    return [$project, $question];
}

it('summarises respondents and progress', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(4)->for($project)->create();
    Candidate::factory()->count(2)->for($project)->create();
    Respondent::factory()->count(3)->for($project)->create();
    Respondent::factory()->for($project)->filled()->create();

    $service = app(DashboardService::class);

    expect($service->summary($project))->toMatchArray([
        'population' => 4, 'respondents' => 4, 'candidates' => 2, 'filled' => 1, 'notFilled' => 3, 'responseRate' => 25.0,
    ])->and($service->progress($project))->toBe([['nama' => 'Total', 'kuota' => 4, 'terisi' => 1, 'persen' => 25.0]]);
});

it('shows progress per wilayah', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Respondent::factory()->count(2)->for($project)->create(['wilayah' => 'B']);
    Respondent::factory()->for($project)->filled()->create(['wilayah' => 'A']);
    Respondent::factory()->for($project)->create(['wilayah' => 'A']);
    Respondent::factory()->for($project)->create(['wilayah' => null]);

    expect(app(DashboardService::class)->progress($project))->toBe([
        ['nama' => 'A', 'kuota' => 2, 'terisi' => 1, 'persen' => 50.0],
        ['nama' => 'B', 'kuota' => 2, 'terisi' => 0, 'persen' => 0.0],
        ['nama' => 'Tanpa Desa', 'kuota' => 1, 'terisi' => 0, 'persen' => 0.0],
    ]);
});

it('compares candidates from the answers', function () {
    [$project, $question] = dashboardFixture();
    foreach (['Ahmad', 'Ahmad', 'Ahmad', 'Budi', null] as $value) {
        answer($question, Respondent::factory()->for($project)->create(), $value);
    }

    $results = app(DashboardService::class)->results($project);

    expect($results)
        ->n->toBe(5)
        ->weighted->toBeFalse()
        ->selisih->toBe(40.0)
        ->and(collect($results['candidates'])->pluck('persen', 'nama')->all())->toBe(['Ahmad' => 60.0, 'Budi' => 20.0])
        ->and($results['others'])->toBe([['nama' => 'Tidak mau menjawab', 'persen' => 20.0]])
        ->and($results['galat'])->toBeGreaterThan(30.0)
        ->and($results['unggul'])->toBeNull();
});

it('declares a leader only when the gap exceeds the margin of error', function () {
    [$project, $question] = dashboardFixture(['sample_population' => 1000]);
    $project->update(['data_mode' => Project::MODE_RESPONDENTS_ONLY]);
    foreach (range(1, 300) as $i) {
        answer($question, Respondent::factory()->for($project)->create(), $i <= 200 ? 'Ahmad' : 'Budi');
    }

    $results = app(DashboardService::class)->results($project->fresh());

    expect($results['n'])->toBe(300)
        ->and($results['selisih'])->toBe(33.4)
        ->and($results['galat'])->toBeBetween(4.0, 5.0)
        ->and($results['unggul'])->toBe('Ahmad');
});

it('computes the actual margin of error with finite population correction', function () {
    $project = Project::factory()->respondentsOnly(1000)->create();
    $service = app(DashboardService::class);

    expect($service->actualMargin($project, 278))->toBeBetween(4.9, 5.0)
        ->and($service->actualMargin($project, 1000))->toBe(0.0)
        ->and($service->actualMargin($project, 0))->toBeNull();
});

it('weights the results by dpt size per wilayah', function () {
    [$project, $question] = dashboardFixture();
    $project->update(['wilayah_label' => 'Desa']);
    Dpt::factory()->count(80)->for($project)->create(['wilayah' => 'A']);
    Dpt::factory()->count(20)->for($project)->create(['wilayah' => 'B']);
    foreach (['Ahmad', 'Budi'] as $value) {
        answer($question, Respondent::factory()->for($project)->create(['wilayah' => 'A']), $value);
    }
    foreach (['Budi', 'Budi'] as $value) {
        answer($question, Respondent::factory()->for($project)->create(['wilayah' => 'B']), $value);
    }
    $service = app(DashboardService::class);

    $raw = collect($service->results($project->fresh())['candidates'])->pluck('persen', 'nama');
    $weighted = $service->results($project->fresh(), true);

    expect($raw->all())->toBe(['Ahmad' => 25.0, 'Budi' => 75.0])
        ->and($weighted['weighted'])->toBeTrue()
        ->and($weighted['canWeight'])->toBeTrue()
        ->and(collect($weighted['candidates'])->pluck('persen', 'nama')->all())->toBe(['Ahmad' => 40.0, 'Budi' => 60.0])
        ->and(collect($weighted['perWilayah'])->pluck('persen', 'nama')->all())->toBe(['A' => 50.0, 'B' => 100.0]);
});

it('has no results without a candidate question', function () {
    $project = Project::factory()->create();

    expect(app(DashboardService::class)->results($project))->toBeNull();
});

it('renders the dashboard page with data', function () {
    [$project, $question] = dashboardFixture();
    answer($question, Respondent::factory()->for($project)->create(), 'Ahmad');

    $this->get(route('dashboard', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Jumlah responden')
        ->assertSee('Perbandingan kandidat')
        ->assertSee('Ahmad')
        ->assertDontSee('Sampel non-acak');
});

it('toggles weighted results in the component', function () {
    [$project, $question] = dashboardFixture();
    $project->update(['wilayah_label' => 'Desa']);
    Dpt::factory()->count(10)->for($project)->create(['wilayah' => 'A']);
    answer($question, Respondent::factory()->for($project)->create(['wilayah' => 'A']), 'Ahmad');

    Livewire::test('livewire-component.dashboard.results-dashboard', ['project' => $project->fresh()])
        ->assertSee('Hasil mentah.')
        ->call('$set', 'weighted', true)
        ->assertSee('Hasil tertimbang');
});

it('warns about a non random sample only in respondents only mode', function () {
    $nonRandom = Project::factory()->respondentsOnly()->create(['is_random_sample' => false]);
    $random = Project::factory()->respondentsOnly()->create();

    $this->get(route('dashboard', ['project' => $nonRandom->code_project]))->assertSee('Sampel non-acak');
    $this->get(route('dashboard', ['project' => $random->code_project]))->assertDontSee('Sampel non-acak');
});
