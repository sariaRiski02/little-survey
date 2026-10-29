<?php

use App\Models\Candidate;
use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use App\Models\RespondentAnswer;
use App\Models\User;
use App\Services\SignificanceService;
use App\Services\Statistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/**
 * @return array{0: Project, 1: Question}
 */
function significanceFixture(array $projectState = [], array $names = ['ahmad', 'budi', 'citra']): array
{
    $project = Project::factory()->published()->state($projectState)->create();
    foreach ($names as $index => $name) {
        Candidate::factory()->for($project)->create(['name' => $name, 'number' => $index + 1]);
    }
    $question = Question::factory()->for($project)->create(['type' => 'kandidat', 'options' => null, 'has_refuse_option' => true]);

    return [$project, $question];
}

function castVotes(Project $project, Question $question, string $name, int $times, ?string $wilayah = null): void
{
    foreach (range(1, $times) as $unused) {
        $respondent = Respondent::factory()->for($project)->filled()->create(['wilayah' => $wilayah]);
        RespondentAnswer::factory()->create(['respondent_id' => $respondent->id, 'question_id' => $question->id, 'values' => [$name]]);
    }
}

it('computes chi-square p-values accurately', function (float $chi2, int $df, float $expected) {
    expect(Statistics::chiSquareSurvival($chi2, $df))->toEqualWithDelta($expected, 0.0005);
})->with([
    'df 1, 5%' => [3.841, 1, 0.05],
    'df 2, 5%' => [5.991, 2, 0.05],
    'df 4, 5%' => [9.488, 4, 0.05],
    'df 1, 1%' => [6.635, 1, 0.01],
    'df 10, 5%' => [18.307, 10, 0.05],
    'df 1, large' => [20.0, 1, 0.0000077],
    'zero' => [0.0, 3, 1.0],
]);

it('computes two-sided p-values of a z statistic', function () {
    expect(Statistics::twoSidedPValue(1.96))->toEqualWithDelta(0.05, 0.0005)
        ->and(Statistics::twoSidedPValue(2.576))->toEqualWithDelta(0.01, 0.0005)
        ->and(Statistics::twoSidedPValue(0.0))->toBe(1.0);
});

it('computes the Wilson interval', function () {
    [$low, $high] = Statistics::wilsonInterval(50, 100, 1.96);

    expect($low)->toEqualWithDelta(0.4038, 0.0005)->and($high)->toEqualWithDelta(0.5962, 0.0005);
    expect(Statistics::wilsonInterval(0, 20, 1.96)[0])->toBe(0.0)
        ->and(Statistics::wilsonInterval(20, 20, 1.96)[1])->toBe(1.0)
        ->and(Statistics::wilsonInterval(0, 0, 1.96))->toBe([0.0, 0.0]);
});

it('computes the z statistic of two proportions from the same sample', function () {
    expect(Statistics::differenceZ(60, 40, 100))->toEqualWithDelta(2.041, 0.001)
        ->and(Statistics::differenceZ(50, 50, 100))->toBe(0.0)
        ->and(Statistics::differenceZ(10, 0, 10))->toBeNull();
});

it('analyzes a clearly significant lead', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 60);
    castVotes($project, $question, 'Budi', 40);

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis)->n->toBe(100)->alpha->toBe(0.05)->confidence->toBe(95)
        ->and($analysis['difference'])->leader->toBe('Ahmad')->runnerUp->toBe('Budi')->selisih->toBe(20.0)->significant->toBeTrue()
        ->and($analysis['difference']['p'])->toEqualWithDelta(0.0412, 0.001)
        ->and($analysis['difference']['conclusion'])->toContain('Ahmad unggul atas Budi secara signifikan')
        ->and($analysis['goodness'])->df->toBe(2)->significant->toBeTrue()
        ->and($analysis['independence'])->toBeNull();

    $ahmad = $analysis['candidates'][0];
    expect($ahmad)->nama->toBe('Ahmad')->persen->toBe(60.0)->vsLeader->toBeNull()
        ->and($ahmad['ciLow'])->toBeLessThan(60.0)->and($ahmad['ciHigh'])->toBeGreaterThan(60.0)
        ->and($analysis['candidates'][1]['vsLeader']['significant'])->toBeTrue()
        ->and($analysis['candidates'][2]['jumlah'])->toBe(0);
});

it('does not call a small gap significant', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 22);
    castVotes($project, $question, 'Budi', 18);

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis['difference'])->significant->toBeFalse()
        ->and($analysis['difference']['p'])->toBeGreaterThan(0.05)
        ->and($analysis['conclusion'])->toContain('tidak signifikan')
        ->and($analysis['candidates'][1]['vsLeader']['significant'])->toBeFalse();
});

it('uses alpha from the confidence level of the parameters', function () {
    [$project, $question] = significanceFixture(['sample_confidence' => 99]);
    castVotes($project, $question, 'Ahmad', 60);
    castVotes($project, $question, 'Budi', 40);

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis['alpha'])->toBe(0.01)->and($analysis['difference']['significant'])->toBeFalse();
});

it('counts refusals in n but not in the vote tests', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 5);
    $respondent = Respondent::factory()->for($project)->filled()->create();
    RespondentAnswer::factory()->create(['respondent_id' => $respondent->id, 'question_id' => $question->id, 'values' => [], 'is_refused' => true]);

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis)->n->toBe(6)->votes->toBe(5);
});

it('tests independence between candidate and wilayah', function () {
    [$project, $question] = significanceFixture(['wilayah_label' => 'Desa'], ['ahmad', 'budi']);
    castVotes($project, $question, 'Ahmad', 30, 'A');
    castVotes($project, $question, 'Budi', 10, 'A');
    castVotes($project, $question, 'Ahmad', 10, 'B');
    castVotes($project, $question, 'Budi', 30, 'B');

    $independence = app(SignificanceService::class)->analyze($project)['independence'];

    expect($independence)->df->toBe(1)->chi2->toBe(20.0)->cramersV->toBe(0.5)->significant->toBeTrue()->reliable->toBeTrue()
        ->and($independence['p'])->toEqualWithDelta(0.0000078, 0.000002)
        ->and($independence['conclusion'])->toBe('Pilihan kandidat berbeda nyata antar Desa.');
});

it('explains why the independence test is unavailable', function () {
    [$project, $question] = significanceFixture(['wilayah_label' => 'Desa'], ['ahmad', 'budi']);
    castVotes($project, $question, 'Ahmad', 3, 'A');
    castVotes($project, $question, 'Budi', 2, 'A');

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis['independence'])->toBeNull()
        ->and($analysis['notes']['independence'])->toContain('minimal dua Desa');
});

it('flags unreliable chi-square results when expected counts are tiny', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 3);
    castVotes($project, $question, 'Budi', 1);

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis['goodness']['reliable'])->toBeFalse()->and($analysis['notes']['goodness'])->toContain('perkiraan kasar');
});

it('handles missing question, missing answers and a single candidate', function () {
    $none = Project::factory()->create();
    expect(app(SignificanceService::class)->analyze($none))->toBeNull();

    [$empty] = significanceFixture();
    expect(app(SignificanceService::class)->analyze($empty))->n->toBe(0)->difference->toBeNull();

    [$single, $question] = significanceFixture([], ['ahmad']);
    castVotes($single, $question, 'Ahmad', 5);
    $analysis = app(SignificanceService::class)->analyze($single);
    expect($analysis['difference'])->toBeNull()->and($analysis['notes']['difference'])->toContain('minimal dua kandidat');
});

it('treats a unanimous result as significant without dividing by zero', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 10);

    $analysis = app(SignificanceService::class)->analyze($project);

    expect($analysis['difference'])->z->toBeNull()->p->toBe(0.0)->significant->toBeTrue();
});

it('shows the significance tests on the statistik page', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 30);
    castVotes($project, $question, 'Budi', 10);

    $this->get(route('statistik', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Uji signifikansi')
        ->assertSee('Uji z selisih dua proporsi')
        ->assertSee('Chi-square kecocokan')
        ->assertSee('Selang kepercayaan 95%')
        ->assertSee('Ahmad unggul atas Budi secara signifikan');
});

it('shows an empty state on the statistik page without answers', function () {
    [$project] = significanceFixture();

    $this->get(route('statistik', ['project' => $project->code_project]))->assertSee('Uji signifikansi')->assertSee('Belum ada jawaban');
});

it('lets the admin switch the dashboard between raw and significance results', function () {
    [$project, $question] = significanceFixture();
    castVotes($project, $question, 'Ahmad', 30);
    castVotes($project, $question, 'Budi', 10);

    Livewire::test('livewire-component.dashboard.results-dashboard', ['project' => $project])
        ->assertSee('Hasil mentah')
        ->assertDontSee('Selang kepercayaan')
        ->call('setView', 'uji')
        ->assertSee('Selang kepercayaan 95%')
        ->assertSee('p-value vs teratas')
        ->call('setView', 'mentah')
        ->assertDontSee('Selang kepercayaan')
        ->call('setView', 'ngawur')
        ->assertSet('view', 'mentah');
});

it('lets guests see both the raw and the significance results', function () {
    [$project, $question] = significanceFixture(['wilayah_label' => 'Desa'], ['ahmad', 'budi']);
    castVotes($project, $question, 'Ahmad', 30, 'A');
    castVotes($project, $question, 'Budi', 10, 'B');

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Hasil mentah')
        ->assertSee('Hasil uji signifikansi')
        ->assertDontSee('Selang kepercayaan');

    Livewire::test('livewire-component.guest.public-results', ['project' => $project])
        ->call('setView', 'uji')
        ->assertSee('Selang kepercayaan 95%')
        ->assertSee('Chi-square independensi')
        ->assertSee('Pilihan kandidat menurut Desa');
});

it('keeps significance results hidden from guests until published', function () {
    [$project, $question] = significanceFixture();
    $project->update(['results_published_at' => null]);
    castVotes($project, $question, 'Ahmad', 30);

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertSee('Survei masih berlangsung')
        ->assertDontSee('Hasil uji signifikansi');
});
