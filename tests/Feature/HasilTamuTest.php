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

const GUEST_RESULTS = 'livewire-component.guest.public-results';

function publishedFixture(array $state = []): array
{
    $project = Project::factory()->published()->state($state)->create(['name' => 'Pilkades Contoh']);
    Candidate::factory()->for($project)->create(['name' => 'ahmad', 'number' => 1]);
    Candidate::factory()->for($project)->create(['name' => 'budi', 'number' => 2]);
    $question = Question::factory()->for($project)->create(['type' => 'kandidat', 'options' => null]);

    return [$project, $question];
}

function vote(Question $question, Project $project, string $name, ?string $wilayah = null): void
{
    $respondent = Respondent::factory()->for($project)->filled()->create(['wilayah' => $wilayah]);
    RespondentAnswer::factory()->create(['respondent_id' => $respondent->id, 'question_id' => $question->id, 'values' => [$name]]);
}

it('tells guests the survey is still running until the admin publishes the results', function () {
    [$project, $question] = publishedFixture();
    $project->update(['results_published_at' => null]);
    vote($question, $project, 'Ahmad');

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Pilkades Contoh')
        ->assertSee('Survei masih berlangsung')
        ->assertDontSee('Perbandingan kandidat')
        ->assertDontSee('Tingkat respons')
        ->assertDontSee('100,0%');
});

it('shows the results to guests once published, without any login', function () {
    [$project, $question] = publishedFixture();
    vote($question, $project, 'Ahmad');
    vote($question, $project, 'Ahmad');
    vote($question, $project, 'Budi');

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertDontSee('Survei masih berlangsung')
        ->assertSee('Perbandingan kandidat')
        ->assertSee('Ahmad')
        ->assertSee('66,7%')
        ->assertSee('Catatan metodologi')
        ->assertSee('Tingkat kepercayaan')
        ->assertSee('noindex', false);
});

it('returns 404 for an unknown project code', function () {
    $this->get(route('hasil', ['project' => 'PRJ-TIDAKADA']))->assertNotFound();
});

it('never exposes individual respondents or their answers', function () {
    [$project, $question] = publishedFixture();
    $respondent = Respondent::factory()->for($project)->filled()->create(['name' => 'Rahasia Sekali', 'nik' => '7171011505900001', 'phone' => '081299998888']);
    RespondentAnswer::factory()->create(['respondent_id' => $respondent->id, 'question_id' => $question->id, 'values' => ['Ahmad']]);

    $html = $this->get(route('hasil', ['project' => $project->code_project]))->assertOk()->getContent();

    expect($html)->not->toContain('Rahasia Sekali')
        ->not->toContain('7171011505900001')
        ->not->toContain('081299998888')
        ->not->toContain($respondent->token);
});

it('shows an empty state when published but nobody has answered yet', function () {
    [$project] = publishedFixture();

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Belum ada data yang dapat ditampilkan');
});

it('warns about a non random sample', function () {
    [$project] = publishedFixture();
    $project->update(['data_mode' => Project::MODE_RESPONDENTS_ONLY, 'is_random_sample' => false]);

    $this->get(route('hasil', ['project' => $project->code_project]))->assertSee('Sampel non-acak');
});

it('hides per wilayah figures for wilayah with fewer than five answers', function () {
    [$project, $question] = publishedFixture(['wilayah_label' => 'Desa']);
    vote($question, $project, 'Ahmad', 'Kecil');
    foreach (range(1, 5) as $i) {
        vote($question, $project, 'Ahmad', 'Besar');
    }

    $component = Livewire::test(GUEST_RESULTS, ['project' => $project->fresh()]);

    $component->assertSee('Suara Ahmad per Desa')
        ->assertSee('Progres pengisian per Desa')
        ->assertSee('Besar')
        ->assertSee('100,0%');

    $perWilayah = collect(app(DashboardService::class)->results($project->fresh())['perWilayah'])->keyBy('nama');
    expect($perWilayah['Kecil']['n'])->toBe(1)->and($perWilayah['Besar']['n'])->toBe(5);
});

it('lets guests toggle the weighted result when it is available', function () {
    [$project, $question] = publishedFixture(['wilayah_label' => 'Desa']);
    Dpt::factory()->count(80)->for($project)->create(['wilayah' => 'A']);
    Dpt::factory()->count(20)->for($project)->create(['wilayah' => 'B']);
    vote($question, $project, 'Ahmad', 'A');
    vote($question, $project, 'Budi', 'A');
    vote($question, $project, 'Budi', 'B');
    vote($question, $project, 'Budi', 'B');

    Livewire::test(GUEST_RESULTS, ['project' => $project->fresh()])
        ->assertSee('Hasil mentah.')
        ->assertSee('Tertimbang')
        ->call('$set', 'weighted', true)
        ->assertSee('Hasil tertimbang menurut jumlah DPT');
});

it('does not require authentication and sets no admin chrome', function () {
    [$project] = publishedFixture();

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertDontSee('Ganti proyek')
        ->assertDontSee('Menu utama');
});

it('lists the candidates for guests with number, color and photo or initials', function () {
    $project = Project::factory()->published()->create();
    Candidate::factory()->for($project)->create(['name' => 'budi rahardjo', 'number' => 2, 'color' => '#D97706', 'picture' => null]);
    Candidate::factory()->for($project)->create(['name' => 'ahmad santoso', 'number' => 1, 'color' => '#0F6B6B', 'picture' => 'pictures/ahmad.jpg']);

    $html = $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Ahmad Santoso')
        ->assertSee('Budi Rahardjo')
        ->assertSee('Nomor urut 1')
        ->assertSee('storage/pictures/ahmad.jpg', false)
        ->assertSee('>BR<', false)
        ->assertDontSee('Belum ada kandidat')
        ->getContent();

    expect(strpos($html, 'Ahmad Santoso'))->toBeLessThan(strpos($html, 'Budi Rahardjo'));
});

it('lists the candidates even while the results are not published', function () {
    $project = Project::factory()->create();
    Candidate::factory()->for($project)->create(['name' => 'ahmad santoso', 'number' => 1]);

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Ahmad Santoso')
        ->assertSee('Survei masih berlangsung')
        ->assertDontSee('Perbandingan kandidat');
});

it('tells guests when there are no candidates yet', function () {
    $project = Project::factory()->create();

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Belum ada kandidat')
        ->assertSee('Daftar kandidat akan ditampilkan setelah penyelenggara menambahkannya.')
        ->assertSee('Survei masih berlangsung');
});

it('does not leak other projects candidates to guests', function () {
    $project = Project::factory()->published()->create();
    Candidate::factory()->create(['name' => 'orang lain', 'number' => 1]);

    $this->get(route('hasil', ['project' => $project->code_project]))
        ->assertDontSee('Orang Lain')
        ->assertSee('Belum ada kandidat');
});
