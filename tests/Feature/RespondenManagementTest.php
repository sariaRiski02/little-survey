<?php

use App\Models\Dpt;
use App\Models\Project;
use App\Models\Respondent;
use App\Models\User;
use App\Services\RespondentService;
use App\Services\StatistikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

const RESP_LIST = 'livewire-component.responden.list-responden';
const RESP_SUMMARY = 'livewire-component.responden.summary-responden';
const RESP_ADD = 'livewire-component.responden.add-responden';
const RESP_IMPORT = 'livewire-component.responden.import-responden';
const RESP_TEMPLATE = 'livewire-component.responden.invitation-template';

function respondentNik(string $date, bool $female = false, string $suffix = '0001'): string
{
    [$year, $month, $day] = explode('-', $date);

    return '717101'.str_pad((string) ((int) $day + ($female ? 40 : 0)), 2, '0', STR_PAD_LEFT).$month.substr($year, 2).$suffix;
}

/**
 * @param  list<list<string>>  $rows
 */
function respondentXlsx(array $rows): File
{
    $path = tempnam(sys_get_temp_dir(), 'resp-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();
    $content = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('responden.xlsx', $content);
}

it('renders the responden page in full dpt mode', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Respondent::factory()->for($project)->create(['name' => 'Budi Santoso', 'wilayah' => 'Sukamaju']);

    $this->get(route('responden', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('R-0001')
        ->assertSee('Belum dikirim')
        ->assertDontSee('Tambah responden');
});

it('renders the responden page with manual actions in respondents only mode', function () {
    $project = Project::factory()->respondentsOnly(500)->create();

    $this->get(route('responden', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Tambah responden')
        ->assertSee('Impor Excel');
});

it('creates respondents from the draw and replaces them on redraw', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(40)->for($project)->create(['phone' => '081234567890']);
    $service = app(StatistikService::class);

    $draw = $service->draw($project);

    $respondents = $project->Respondent()->get();
    expect($respondents)->toHaveCount($draw->selected_count)
        ->and($respondents->pluck('dpt_id')->sort()->values()->all())->toBe(Dpt::where('is_selected', true)->pluck('id')->sort()->values()->all())
        ->and($respondents->pluck('number')->all())->toBe(range(1, $draw->selected_count))
        ->and($respondents->pluck('token')->unique())->toHaveCount($draw->selected_count)
        ->and($respondents->first())->phone->toBe('081234567890')->link_sent_at->toBeNull();

    $oldTokens = $respondents->pluck('token');
    $service->redraw($project, 'Ada kesalahan data');

    expect($project->Respondent()->count())->toBe($project->SampleDraws()->latest('id')->first()->selected_count)
        ->and($project->Respondent()->pluck('token')->intersect($oldTokens))->toBeEmpty();
});

it('refuses to redraw once a respondent has filled the survey', function () {
    $project = Project::factory()->create();
    Dpt::factory()->count(30)->for($project)->create();
    $service = app(StatistikService::class);
    $service->draw($project);
    $project->Respondent()->first()->update(['filled_at' => now()]);

    expect(fn () => $service->redraw($project, 'Mau diundi ulang'))->toThrow(ValidationException::class);
    expect($project->SampleDraws()->count())->toBe(1);
});

it('shows the summary counts', function () {
    $project = Project::factory()->create();
    Respondent::factory()->for($project)->create();
    Respondent::factory()->for($project)->sent()->create();
    Respondent::factory()->for($project)->filled()->create();
    Respondent::factory()->create();

    expect(app(RespondentService::class)->summary($project))->toBe(['total' => 3, 'filled' => 1, 'notFilled' => 2, 'notSent' => 1]);

    Livewire::test(RESP_SUMMARY, ['project' => $project])->assertSee('Total responden')->assertSee('Tautan belum dikirim');
});

it('searches, filters and paginates the list', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Respondent::factory()->count(11)->for($project)->create(['wilayah' => 'A']);
    $target = Respondent::factory()->for($project)->sent()->create(['name' => 'Zainal Unik', 'wilayah' => 'B']);
    Respondent::factory()->for($project)->filled()->create(['wilayah' => 'A']);

    $component = Livewire::test(RESP_LIST, ['project' => $project]);
    expect($component->instance()->rows->total())->toBe(13)->and($component->instance()->rows)->toHaveCount(10);

    $component->set('search', 'zainal');
    expect($component->instance()->rows->pluck('id')->all())->toBe([$target->id]);

    $component->set('search', $target->code());
    expect($component->instance()->rows->pluck('id')->all())->toBe([$target->id]);

    $component->set('search', '')->set('filterWilayah', 'B');
    expect($component->instance()->rows->total())->toBe(1);

    $component->set('filterWilayah', '')->set('filterStatus', 'mengisi');
    expect($component->instance()->rows->total())->toBe(1);

    $component->set('filterStatus', 'terkirim');
    expect($component->instance()->rows->pluck('id')->all())->toBe([$target->id]);

    $component->set('filterStatus', 'belum');
    expect($component->instance()->rows->total())->toBe(11);
});

it('marks a link as sent and locks candidates and questions', function () {
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->create();

    Livewire::test(RESP_LIST, ['project' => $project])
        ->call('markSent', $respondent->id)
        ->assertHasNoErrors()
        ->assertDispatched('respondent-changed');

    expect($respondent->fresh()->status())->toBe('terkirim');
    expect($project->fresh())->is_locked->toBeTrue()->is_questions_locked->toBeTrue();
});

it('keeps the first sent time when marking again', function () {
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->create(['link_sent_at' => now()->subDays(3)]);
    $sentAt = $respondent->fresh()->link_sent_at;

    app(RespondentService::class)->markSent($project, $respondent->id);

    expect($respondent->fresh()->link_sent_at->equalTo($sentAt))->toBeTrue();
});

it('regenerates a link and invalidates the old one', function () {
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->sent()->create();
    $oldToken = $respondent->token;

    Livewire::test(RESP_LIST, ['project' => $project])->call('regenerate', $respondent->id)->assertHasNoErrors();

    $respondent->refresh();
    expect($respondent->token)->not->toBe($oldToken)->and($respondent->link_sent_at)->toBeNull()
        ->and(Respondent::where('token', $oldToken)->exists())->toBeFalse();
});

it('cannot regenerate or delete a respondent who already filled', function () {
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->filled()->create();

    Livewire::test(RESP_LIST, ['project' => $project])->call('regenerate', $respondent->id)->assertHasErrors('project');
    Livewire::test(RESP_LIST, ['project' => $project])->call('delete', $respondent->id)->assertHasErrors('project');

    expect(Respondent::find($respondent->id))->not->toBeNull();
});

it('deletes a respondent and unselects the matching dpt row', function () {
    $project = Project::factory()->create();
    $dpt = Dpt::factory()->selected()->for($project)->create();
    $respondent = Respondent::factory()->for($project)->create(['dpt_id' => $dpt->id]);

    Livewire::test(RESP_LIST, ['project' => $project])
        ->call('delete', $respondent->id)
        ->assertDispatched('respondent-saved');

    expect(Respondent::find($respondent->id))->toBeNull()->and($dpt->fresh()->is_selected)->toBeFalse();
});

it('cannot touch a respondent from another project', function () {
    $project = Project::factory()->create();
    $other = Respondent::factory()->create();

    foreach (['markSent', 'regenerate', 'delete'] as $action) {
        Livewire::test(RESP_LIST, ['project' => $project])->call($action, $other->id)->assertNotFound();
    }

    expect($other->fresh()->link_sent_at)->toBeNull();
});

it('builds a whatsapp link from the invitation template', function () {
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->create(['name' => 'Budi', 'phone' => '0812-3456-7890']);
    $service = app(RespondentService::class);

    $message = $service->invitationMessage($project, $respondent);

    expect($respondent->whatsappNumber())->toBe('6281234567890')
        ->and($message)->toContain('Halo Budi')->toContain($respondent->surveyUrl())
        ->and($service->whatsappUrl($project, $respondent))->toStartWith('https://wa.me/6281234567890?text='.rawurlencode('Halo Budi'));

    expect($service->whatsappUrl($project, Respondent::factory()->for($project)->create(['phone' => null])))->toBeNull();
});

it('saves a custom invitation template and requires the link placeholder', function () {
    $project = Project::factory()->create();

    Livewire::test(RESP_TEMPLATE, ['project' => $project])
        ->set('template', 'Tanpa penanda tautan')
        ->call('save')
        ->assertHasErrors('template');

    Livewire::test(RESP_TEMPLATE, ['project' => $project])
        ->set('template', 'Hai {nama}, isi survei: {tautan}')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('template-saved');

    expect($project->fresh()->invitation_template)->toBe('Hai {nama}, isi survei: {tautan}');
});

it('adds a respondent manually in respondents only mode', function () {
    $project = Project::factory()->respondentsOnly(500)->withWilayah('Desa')->create();
    Respondent::factory()->for($project)->create();

    Livewire::test(RESP_ADD, ['project' => $project])
        ->set('name', ' Siti ')
        ->set('nik', respondentNik('1992-03-09', true))
        ->set('birth_date', '1992-03-09')
        ->set('gender', 'Perempuan')
        ->set('wilayah', 'Mekarsari')
        ->set('phone', '081234567890')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('respondent-added')
        ->assertSet('name', '');

    expect($project->Respondent()->orderByDesc('number')->first())
        ->name->toBe('Siti')->number->toBe(2)->wilayah->toBe('Mekarsari')
        ->and(strlen($project->Respondent()->orderByDesc('number')->first()->token))->toBe(40);
});

it('validates manual respondent input', function () {
    $project = Project::factory()->respondentsOnly(500)->withWilayah('Desa')->create();
    $nik = respondentNik('1990-05-15');
    Respondent::factory()->for($project)->create(['nik' => $nik]);

    Livewire::test(RESP_ADD, ['project' => $project])
        ->set('nik', '123')
        ->call('save')
        ->assertHasErrors(['name', 'nik', 'birth_date', 'gender', 'wilayah']);

    Livewire::test(RESP_ADD, ['project' => $project])
        ->set('name', 'Dobel')->set('nik', $nik)->set('birth_date', '1990-05-15')->set('gender', 'Laki-laki')->set('wilayah', 'A')
        ->call('save')
        ->assertHasErrors('nik');

    Livewire::test(RESP_ADD, ['project' => $project])
        ->set('name', 'Beda Tanggal')->set('nik', respondentNik('1990-05-15', false, '0002'))->set('birth_date', '1991-05-15')->set('gender', 'Laki-laki')->set('wilayah', 'A')
        ->call('save')
        ->assertHasNoErrors();
});

it('cannot add or import respondents in full dpt mode', function () {
    $project = Project::factory()->create();

    Livewire::test(RESP_ADD, ['project' => $project])
        ->set('name', 'Budi')->set('nik', respondentNik('1990-05-15'))->set('birth_date', '1990-05-15')->set('gender', 'Laki-laki')
        ->call('save')
        ->assertHasErrors('project');

    Livewire::test(RESP_IMPORT, ['project' => $project])
        ->set('file', respondentXlsx([['Nama', 'NIK', 'Tanggal lahir', 'Jenis kelamin'], ['Budi', respondentNik('1990-05-15'), '15/05/1990', 'L']]))
        ->call('import')
        ->assertHasErrors('project');

    expect(Respondent::count())->toBe(0);
});

it('imports only the valid rows from excel', function () {
    $project = Project::factory()->respondentsOnly(500)->create();
    Respondent::factory()->for($project)->create(['nik' => respondentNik('1985-01-01')]);

    $component = Livewire::test(RESP_IMPORT, ['project' => $project])
        ->set('file', respondentXlsx([
            ['Nama', 'NIK', 'Tanggal Lahir', 'JK', 'Sosmed', 'Telepon'],
            ['Budi', respondentNik('1990-05-15'), '15/05/1990', 'L', '@budi', '081111111111'],
            ['Siti', respondentNik('1992-03-09', true), '09-03-1992', 'P', '', ''],
            ['Tanpa JK', respondentNik('1990-05-15', false, '0002'), '15/05/1990', '', '', ''],
            ['Sudah Ada', respondentNik('1985-01-01'), '01/01/1985', 'L', '', ''],
            ['Dobel', respondentNik('1990-05-15'), '15/05/1990', 'L', '', ''],
        ]))
        ->assertHasNoErrors()
        ->assertSet('mapping', [0 => 'name', 1 => 'nik', 2 => 'birth_date', 3 => 'gender', 4 => 'social_media', 5 => 'phone']);

    $analysis = $component->instance()->analysis;
    expect($analysis['valid'])->toHaveCount(2)
        ->and(collect($analysis['problems'])->pluck('row')->all())->toBe([4, 5, 6]);

    $component->call('import')->assertHasNoErrors()->assertSet('importedCount', 2)->assertDispatched('respondent-changed');

    $numbers = $project->Respondent()->orderBy('number')->pluck('number')->all();
    expect($numbers)->toBe([1, 2, 3])
        ->and($project->Respondent()->where('name', 'Budi')->first())->social_media->toBe('@budi')->gender->toBe('Laki-laki');
});

it('requires the required columns to be mapped before importing', function () {
    $project = Project::factory()->respondentsOnly(500)->create();

    $component = Livewire::test(RESP_IMPORT, ['project' => $project])
        ->set('file', respondentXlsx([['Kolom A'], ['Budi']]));

    expect($component->instance()->analysis['missingFields'])->toBe(['Nama', 'NIK', 'Tanggal lahir', 'Jenis kelamin']);

    $component->call('import')->assertHasErrors('mapping');
    expect(Respondent::count())->toBe(0);
});

it('downloads the excel template only in respondents only mode', function () {
    $manual = Project::factory()->respondentsOnly(100)->create();
    $full = Project::factory()->create();

    $this->get(route('responden.template', ['project' => $manual->code_project]))->assertOk();
    $this->get(route('responden.template', ['project' => $full->code_project]))->assertNotFound();
});

it('exports the links as csv without formula injection', function () {
    $project = Project::factory()->create();
    Respondent::factory()->for($project)->create(['name' => '=HYPERLINK("http://evil")', 'phone' => '081234567890']);
    Respondent::factory()->for($project)->filled()->create(['name' => 'Budi']);
    Respondent::factory()->create(['name' => 'Project Lain']);

    $response = $this->get(route('responden.export', ['project' => $project->code_project]))->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Kode,Nama')
        ->toContain("\"'=HYPERLINK(\"\"http://evil\"\")\"")
        ->toContain('Budi')
        ->toContain('/s/')
        ->not->toContain('Project Lain');
});

it('exports the wilayah column only when the project uses wilayah', function () {
    $plain = Project::factory()->create();
    Respondent::factory()->for($plain)->create(['name' => 'Tanpa Jaga']);

    $withWilayah = Project::factory()->withWilayah('Jaga')->create();
    Respondent::factory()->for($withWilayah)->create(['name' => 'Budi', 'wilayah' => 'Jaga 1']);

    $plainCsv = $this->get(route('responden.export', ['project' => $plain->code_project]))->streamedContent();
    $wilayahCsv = $this->get(route('responden.export', ['project' => $withWilayah->code_project]))->streamedContent();

    expect($plainCsv)->toContain('Kode,Nama,"Nomor telepon"')->not->toContain('Wilayah')
        ->and($wilayahCsv)->toContain('Kode,Nama,Jaga,')->toContain('Budi,"Jaga 1"');
});

it('exports only the links matching the wilayah and status filters', function () {
    $project = Project::factory()->withWilayah('Jaga')->create();
    Respondent::factory()->for($project)->create(['name' => 'Belum Satu', 'wilayah' => 'Jaga 1']);
    Respondent::factory()->for($project)->create(['name' => 'Belum Dua', 'wilayah' => 'Jaga 2']);
    Respondent::factory()->for($project)->sent()->create(['name' => 'Terkirim Satu', 'wilayah' => 'Jaga 1']);
    Respondent::factory()->for($project)->filled()->create(['name' => 'Selesai Satu', 'wilayah' => 'Jaga 1']);

    $export = fn (array $query) => $this->get(route('responden.export', ['project' => $project->code_project] + $query))->assertOk()->streamedContent();

    expect($export(['wilayah' => 'Jaga 1']))->toContain('Belum Satu', 'Terkirim Satu', 'Selesai Satu')->not->toContain('Belum Dua')
        ->and($export(['status' => 'belum']))->toContain('Belum Satu', 'Belum Dua')->not->toContain('Terkirim Satu')->not->toContain('Selesai Satu')
        ->and($export(['wilayah' => 'Jaga 2', 'status' => 'belum']))->toContain('Belum Dua')->not->toContain('Belum Satu')
        ->and($export([]))->toContain('Belum Satu', 'Belum Dua', 'Terkirim Satu', 'Selesai Satu');

    $this->get(route('responden.export', ['project' => $project->code_project, 'status' => 'ngawur']))->assertSessionHasErrors('status');
});
