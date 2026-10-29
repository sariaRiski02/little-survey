<?php

use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use App\Models\RespondentAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

const JAWABAN_LIST = 'livewire-component.jawaban.list-jawaban';

/**
 * @return list<list<mixed>>
 */
function readExport($response): array
{
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }

        break;
    }

    $reader->close();

    return $rows;
}

function answered(Respondent $respondent, Question $question, array $values, bool $refused = false): void
{
    RespondentAnswer::create([
        'respondent_id' => $respondent->id,
        'question_id' => $question->id,
        'values' => $values,
        'is_refused' => $refused,
    ]);
}

it('lists the answers of respondents who have filled the survey', function () {
    $project = Project::factory()->create();
    $single = Question::factory()->for($project)->create(['text' => 'Pilihan Anda?', 'position' => 1]);
    $multiple = Question::factory()->for($project)->create(['text' => 'Isu apa?', 'type' => Question::TYPE_MULTIPLE, 'options' => ['A', 'B', 'C'], 'position' => 2]);
    $text = Question::factory()->for($project)->create(['text' => 'Saran?', 'type' => Question::TYPE_TEXT, 'options' => null, 'is_required' => false, 'position' => 3]);

    $budi = Respondent::factory()->for($project)->filled()->create(['name' => 'Budi Santoso']);
    answered($budi, $single, ['Ya']);
    answered($budi, $multiple, ['A', 'C']);
    answered($budi, $text, ['Perbaiki jalan desa']);

    $siti = Respondent::factory()->for($project)->filled()->create(['name' => 'Siti Aminah']);
    answered($siti, $single, [], true);

    Respondent::factory()->for($project)->create(['name' => 'Belum Mengisi']);
    Respondent::factory()->filled()->create(['name' => 'Proyek Lain']);

    $this->get(route('jawaban', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Pilihan Anda?');

    Livewire::test(JAWABAN_LIST, ['project' => $project])
        ->assertSee('Budi Santoso')
        ->assertSee('Ya')
        ->assertSee('A; C')
        ->assertSee('Perbaiki jalan desa')
        ->assertSee('Siti Aminah')
        ->assertSee('Tidak mau menjawab')
        ->assertDontSee('Belum Mengisi')
        ->assertDontSee('Proyek Lain');
});

it('searches and filters the answers', function () {
    $project = Project::factory()->withWilayah('Jaga')->create();
    $question = Question::factory()->for($project)->create();
    $budi = Respondent::factory()->for($project)->filled()->create(['name' => 'Budi', 'wilayah' => 'Jaga 1']);
    $siti = Respondent::factory()->for($project)->filled()->create(['name' => 'Siti', 'wilayah' => 'Jaga 2']);
    answered($budi, $question, ['Ya']);
    answered($siti, $question, ['Tidak']);

    Livewire::test(JAWABAN_LIST, ['project' => $project])
        ->set('search', 'Siti')->assertSee('Siti')->assertDontSee('Budi')
        ->set('search', '')->set('filterWilayah', 'Jaga 1')->assertSee('Budi')->assertDontSee('Siti');
});

it('exports every answer to excel with one column per question', function () {
    $project = Project::factory()->withWilayah('Jaga')->create();
    $single = Question::factory()->for($project)->create(['text' => 'Pilihan Anda?', 'position' => 1]);
    $multiple = Question::factory()->for($project)->create(['text' => 'Isu apa?', 'type' => Question::TYPE_MULTIPLE, 'options' => ['A', 'B'], 'position' => 2]);

    $budi = Respondent::factory()->for($project)->filled()->create(['name' => 'Budi', 'wilayah' => 'Jaga 1', 'nik' => '7171011505900001', 'phone' => '081234567890']);
    answered($budi, $single, ['Ya']);
    answered($budi, $multiple, ['A', 'B']);
    $siti = Respondent::factory()->for($project)->filled()->create(['name' => 'Siti', 'wilayah' => 'Jaga 2']);
    answered($siti, $single, [], true);
    Respondent::factory()->for($project)->create(['name' => 'Belum Mengisi']);

    $rows = readExport($this->get(route('jawaban.export', ['project' => $project->code_project]))->assertOk());

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe(['Kode', 'Nama', 'Jaga', 'Waktu mengisi', '1. Pilihan Anda?', '2. Isu apa?'])
        ->and([$rows[1][0], $rows[1][1], $rows[1][2], $rows[1][4], $rows[1][5]])->toBe(['R-0001', 'Budi', 'Jaga 1', 'Ya', 'A; B'])
        ->and([$rows[2][1], $rows[2][4], $rows[2][5] ?? ''])->toBe(['Siti', 'Tidak mau menjawab', '']);

    expect(json_encode($rows))->not->toContain('7171011505900001')->not->toContain('081234567890')->not->toContain('Belum Mengisi');
});

it('exports only the chosen wilayah and leaves the wilayah column out when unused', function () {
    $project = Project::factory()->withWilayah('Jaga')->create();
    $question = Question::factory()->for($project)->create();
    foreach (['Jaga 1' => 'Budi', 'Jaga 2' => 'Siti'] as $wilayah => $name) {
        answered(Respondent::factory()->for($project)->filled()->create(['name' => $name, 'wilayah' => $wilayah]), $question, ['Ya']);
    }

    $rows = readExport($this->get(route('jawaban.export', ['project' => $project->code_project, 'wilayah' => 'Jaga 2']))->assertOk());

    expect($rows)->toHaveCount(2)->and($rows[1][1])->toBe('Siti');

    $plain = Project::factory()->create();
    $plainQuestion = Question::factory()->for($plain)->create(['text' => 'Tanya?']);
    answered(Respondent::factory()->for($plain)->filled()->create(['name' => 'Andi']), $plainQuestion, ['Ya']);

    $rows = readExport($this->get(route('jawaban.export', ['project' => $plain->code_project]))->assertOk());

    expect($rows[0])->toBe(['Kode', 'Nama', 'Waktu mengisi', '1. Tanya?']);
});

it('only exports the answers of the requested project', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $question = Question::factory()->for($other)->create();
    answered(Respondent::factory()->for($other)->filled()->create(['name' => 'Orang Lain']), $question, ['Ya']);

    $rows = readExport($this->get(route('jawaban.export', ['project' => $project->code_project]))->assertOk());

    expect($rows)->toHaveCount(1);
});
