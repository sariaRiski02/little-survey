<?php

use App\Models\Dpt;
use App\Models\Project;
use App\Models\Respondent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const SETTINGS_FORM = 'livewire-component.pengaturan.settings-form';

it('renders the settings page with the current values', function () {
    $project = Project::factory()->withWilayah('Dusun')->create(['name' => 'Pilkades Contoh']);

    $this->get(route('pengaturan', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Pilkades Contoh')
        ->assertSee('Dusun');
});

it('saves the name and the wilayah label', function () {
    $project = Project::factory()->withWilayah('Desa')->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])
        ->set('name', '  Survei Baru  ')
        ->set('wilayah_label', 'Jaga')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pengaturan', ['project' => $project->code_project]));

    expect($project->fresh())->name->toBe('Survei Baru')->wilayah_label->toBe('Jaga');
});

it('clears the wilayah label when left blank', function () {
    $project = Project::factory()->withWilayah('Desa')->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])->set('wilayah_label', '  ')->call('save')->assertHasNoErrors();

    expect($project->fresh()->wilayah_label)->toBeNull()->and($project->fresh()->usesWilayah())->toBeFalse();
});

it('validates the settings', function () {
    $project = Project::factory()->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])
        ->set('name', '')
        ->set('wilayah_label', str_repeat('x', 51))
        ->set('data_mode', 'lainnya')
        ->call('save')
        ->assertHasErrors(['name', 'wilayah_label', 'data_mode']);

    Livewire::test(SETTINGS_FORM, ['project' => $project])
        ->set('data_mode', 'responden_saja')
        ->set('population', '-5')
        ->call('save')
        ->assertHasErrors('population');
});

it('switches to respondents only mode and stores the extra settings', function () {
    $project = Project::factory()->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])
        ->set('data_mode', 'responden_saja')
        ->set('population', '2500')
        ->set('is_random_sample', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($project->fresh())
        ->data_mode->toBe('responden_saja')
        ->sample_population->toBe(2500)
        ->is_random_sample->toBeFalse();
});

it('accepts an empty population in respondents only mode', function () {
    $project = Project::factory()->respondentsOnly(900)->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])->set('population', '')->call('save')->assertHasNoErrors();

    expect($project->fresh()->sample_population)->toBeNull();
});

it('does not allow switching the mode once data exists', function () {
    $withDpt = Project::factory()->create();
    Dpt::factory()->for($withDpt)->create();
    $withRespondent = Project::factory()->respondentsOnly(100)->create();
    Respondent::factory()->for($withRespondent)->create();

    Livewire::test(SETTINGS_FORM, ['project' => $withDpt])->set('data_mode', 'responden_saja')->call('save')->assertHasErrors('data_mode');
    Livewire::test(SETTINGS_FORM, ['project' => $withRespondent])->set('data_mode', 'dpt_lengkap')->call('save')->assertHasErrors('data_mode');

    expect($withDpt->fresh()->data_mode)->toBe('dpt_lengkap')->and($withRespondent->fresh()->data_mode)->toBe('responden_saja');
});

it('still allows other changes when the mode is locked by data', function () {
    $project = Project::factory()->create();
    Dpt::factory()->for($project)->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])->set('name', 'Nama Lain')->call('save')->assertHasNoErrors();

    expect($project->fresh()->name)->toBe('Nama Lain');
});

it('hides the dpt menu and page in respondents only mode', function () {
    $full = Project::factory()->create();
    $manual = Project::factory()->respondentsOnly(100)->create();

    $this->get(route('pengaturan', ['project' => $full->code_project]))->assertSee(route('dpt', ['project' => $full->code_project]), false);
    $this->get(route('pengaturan', ['project' => $manual->code_project]))->assertDontSee('/dpt', false);
    $this->get(route('dpt', ['project' => $manual->code_project]))->assertNotFound();
    $this->get(route('dpt.template', ['project' => $manual->code_project]))->assertNotFound();
});

it('publishes and withdraws the results for guests', function () {
    $project = Project::factory()->create();

    Livewire::test(SETTINGS_FORM, ['project' => $project])->set('publish_results', true)->call('save')->assertHasNoErrors();
    $publishedAt = $project->fresh()->results_published_at;

    expect($publishedAt)->not->toBeNull()->and($project->fresh()->resultsArePublished())->toBeTrue();

    $this->travel(1)->hour();
    Livewire::test(SETTINGS_FORM, ['project' => $project->fresh()])->set('name', 'Nama Lain')->call('save');
    expect($project->fresh()->results_published_at->equalTo($publishedAt))->toBeTrue();

    Livewire::test(SETTINGS_FORM, ['project' => $project->fresh()])->set('publish_results', false)->call('save');
    expect($project->fresh()->results_published_at)->toBeNull();
});

it('shows the public results link on the settings page', function () {
    $project = Project::factory()->create();

    $this->get(route('pengaturan', ['project' => $project->code_project]))
        ->assertSee(route('hasil', ['project' => $project->code_project]), false)
        ->assertSee('Survei selesai, publikasikan hasil');
});
