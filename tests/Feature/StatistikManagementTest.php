<?php

use App\Models\Candidate;
use App\Models\Dpt;
use App\Models\Project;
use App\Models\SampleDraw;
use App\Models\User;
use App\Services\StatistikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

const STAT_PARAMETER = 'livewire-component.statistik.sample-parameter';
const STAT_RESULT = 'livewire-component.statistik.sample-result';
const STAT_DRAW = 'livewire-component.statistik.draw-panel';

function defaultParams(array $overrides = []): array
{
    return $overrides + ['confidence' => 95, 'margin_error' => 5.0, 'proportion' => 0.5, 'reserve' => 20];
}

it('calculates the sample size with Cochran and finite population correction', function () {
    $result = app(StatistikService::class)->calculate(1000, defaultParams());

    expect($result['sampleSize'])->toBe(278)
        ->and($result['invitations'])->toBe(348)
        ->and($result['steps'])->toHaveCount(4)
        ->and($result['steps'][1]['hasil'])->toBe('384,16');

    expect(app(StatistikService::class)->calculate(100000, defaultParams(['confidence' => 99, 'margin_error' => 3.0]))['sampleSize'])->toBe(1810);
});

it('never asks for more than the population', function () {
    $result = app(StatistikService::class)->calculate(10, defaultParams());

    expect($result['sampleSize'])->toBeLessThanOrEqual(10)->and($result['invitations'])->toBe(10);
    expect(app(StatistikService::class)->calculate(0, defaultParams()))->toBeNull();
});

it('renders the statistik page in full dpt mode', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Dpt::factory()->count(5)->for($project)->create(['wilayah' => 'Sukamaju']);

    $this->get(route('statistik', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Hasil hitung')
        ->assertSee('Alokasi per Desa')
        ->assertSee('Sukamaju')
        ->assertSee('Undi acak');
});

it('renders the statistik page as a calculator in respondents only mode', function () {
    $project = Project::factory()->respondentsOnly(1000)->create();

    $this->get(route('statistik', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('kalkulator ukuran sampel')
        ->assertSee('278')
        ->assertDontSee('Undi acak');
});

it('saves parameters, accepting decimal commas', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(3)->for($project)->create();

    Livewire::test(STAT_PARAMETER, ['project' => $project])
        ->set('confidence', '99')
        ->set('margin_error', '2,5')
        ->set('proportion', '0,3')
        ->set('reserve', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('sample-params-saved');

    expect($project->fresh())
        ->sample_confidence->toBe(99)
        ->sample_margin_error->toBe(2.5)
        ->sample_proportion->toBe(0.3)
        ->sample_reserve->toBe(10);
});

it('validates parameters', function () {
    $project = Project::factory()->create();

    Livewire::test(STAT_PARAMETER, ['project' => $project])
        ->set('confidence', '80')
        ->set('margin_error', '0')
        ->set('proportion', '1')
        ->set('reserve', '95')
        ->call('save')
        ->assertHasErrors(['confidence', 'margin_error', 'proportion', 'reserve']);

    expect($project->fresh()->sample_confidence)->toBe(95);
});

it('requires a manual population in respondents only mode', function () {
    $project = Project::factory()->respondentsOnly()->create();

    Livewire::test(STAT_PARAMETER, ['project' => $project])
        ->call('save')
        ->assertHasErrors('population');

    Livewire::test(STAT_PARAMETER, ['project' => $project])
        ->set('population', '2500')
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh()->sample_population)->toBe(2500);
});

it('allocates the sample proportionally per wilayah', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    foreach (['A' => 500, 'B' => 300, 'C' => 200] as $wilayah => $count) {
        Dpt::factory()->count($count)->for($project)->create(['wilayah' => $wilayah]);
    }
    $service = app(StatistikService::class);
    $calculation = $service->calculate(1000, defaultParams());

    $allocation = $service->allocation($project, $calculation);

    expect(array_column($allocation['rows'], 'dpt'))->toBe([500, 300, 200])
        ->and(array_column($allocation['rows'], 'sampel'))->toBe([139, 83, 56])
        ->and($allocation['total']['sampel'])->toBe(278)
        ->and($allocation['total']['undangan'])->toBeGreaterThanOrEqual(348)
        ->and($allocation['total']['persen'])->toBe(100.0);
});

it('draws a simple random sample and locks the dpt and candidates', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(50)->for($project)->create();
    Candidate::factory()->for($project)->create();
    $expected = app(StatistikService::class)->calculate(50, defaultParams())['invitations'];

    Livewire::test(STAT_DRAW, ['project' => $project])
        ->call('draw')
        ->assertHasNoErrors()
        ->assertDispatched('sample-drawn');

    $project->refresh();
    $draw = $project->SampleDraws()->sole();

    expect($project->is_dpt_locked)->toBeTrue()
        ->and($project->is_locked)->toBeTrue()
        ->and(Dpt::where('is_selected', true)->count())->toBe($expected)
        ->and($draw)->selected_count->toBe($expected)->population->toBe(50)->reason->toBeNull();
});

it('draws per wilayah quota', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    foreach (['A' => 400, 'B' => 600] as $wilayah => $count) {
        Dpt::factory()->count($count)->for($project)->create(['wilayah' => $wilayah]);
    }
    $service = app(StatistikService::class);
    $quotas = collect($service->allocation($project, $service->calculate(1000, defaultParams()))['rows'])->pluck('undangan', 'key');

    $service->draw($project);

    expect(Dpt::where('wilayah', 'A')->where('is_selected', true)->count())->toBe($quotas['A'])
        ->and(Dpt::where('wilayah', 'B')->where('is_selected', true)->count())->toBe($quotas['B']);
});

it('produces the same selection for the same seed', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(60)->for($project)->create();
    $service = app(StatistikService::class);

    $service->draw($project, 12345);
    $first = Dpt::where('is_selected', true)->pluck('id')->sort()->values()->all();

    $service->redraw($project, 'Pengujian seed yang sama', 12345);
    $second = Dpt::where('is_selected', true)->pluck('id')->sort()->values()->all();

    expect($second)->toBe($first);

    $service->redraw($project, 'Pengujian seed berbeda', 999);
    expect(Dpt::where('is_selected', true)->count())->toBe(count($first));
});

it('refuses to draw twice and records redraws with a reason', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(30)->for($project)->create();
    $service = app(StatistikService::class);

    $service->draw($project);

    expect(fn () => $service->draw($project))->toThrow(ValidationException::class);
    expect(fn () => $service->redraw($project, '   '))->toThrow(ValidationException::class);

    $service->redraw($project, 'Ada kesalahan data');

    $draws = $project->SampleDraws()->orderBy('id')->get();
    expect($draws)->toHaveCount(2)
        ->and($draws[1]->reason)->toBe('Ada kesalahan data')
        ->and($draws[1]->isRedraw())->toBeTrue()
        ->and(Dpt::where('is_selected', true)->count())->toBe($draws[1]->selected_count);
});

it('requires a reason and confirmation in the redraw form', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(30)->for($project)->create();
    app(StatistikService::class)->draw($project);

    Livewire::test(STAT_DRAW, ['project' => $project->fresh()])
        ->call('redraw')
        ->assertHasErrors(['reason', 'understood']);

    Livewire::test(STAT_DRAW, ['project' => $project->fresh()])
        ->set('reason', 'Responden tidak sesuai')
        ->set('understood', true)
        ->call('redraw')
        ->assertHasNoErrors()
        ->assertDispatched('redraw-done');

    expect($project->SampleDraws()->count())->toBe(2);
});

it('cannot redraw before the first draw', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(5)->for($project)->create();

    expect(fn () => app(StatistikService::class)->redraw($project, 'Alasan apa saja'))->toThrow(ValidationException::class);
});

it('cannot draw with an empty dpt', function () {
    $project = Project::factory()->create();

    Livewire::test(STAT_DRAW, ['project' => $project])->call('draw')->assertHasErrors('project');

    expect(SampleDraw::count())->toBe(0)->and($project->fresh()->is_dpt_locked)->toBeFalse();
});

it('cannot draw in respondents only mode', function () {
    $project = Project::factory()->respondentsOnly(1000)->create();

    Livewire::test(STAT_DRAW, ['project' => $project])->call('draw')->assertHasErrors('project');

    expect(SampleDraw::count())->toBe(0);
});

it('does not touch dpt rows of other projects when drawing', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(20)->for($project)->create();
    $other = Dpt::factory()->create(['is_selected' => true]);

    app(StatistikService::class)->draw($project);

    expect($other->fresh()->is_selected)->toBeTrue();
});

it('refreshes the result component after parameters change', function () {
    $project = Project::factory()->respondentsOnly(1000)->create();

    $component = Livewire::test(STAT_RESULT, ['project' => $project])->assertSee('278');

    $project->update(['sample_margin_error' => 10]);
    $component->dispatch('sample-params-saved')->assertDontSee('278');
});
