<?php

use App\Models\Dpt;
use App\Models\Project;
use App\Models\User;
use App\Services\DptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

const DPT_LIST = 'livewire-component.dpt.list-dpt';
const DPT_ADD = 'livewire-component.dpt.add-dpt';
const DPT_IMPORT = 'livewire-component.dpt.import-dpt';
const DPT_SUMMARY = 'livewire-component.dpt.summary-dpt';

function nikFor(string $date, bool $female = false, string $suffix = '0001'): string
{
    [$year, $month, $day] = explode('-', $date);

    return '717101'.str_pad((string) ((int) $day + ($female ? 40 : 0)), 2, '0', STR_PAD_LEFT).$month.substr($year, 2).$suffix;
}

/**
 * @param  list<list<string>>  $rows
 */
function xlsxUpload(array $rows): File
{
    $path = tempnam(sys_get_temp_dir(), 'dpt-test-');
    $writer = new Writer;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    $content = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('dpt.xlsx', $content);
}

it('renders the dpt page with data from the database', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Dpt::factory()->for($project)->create(['name' => 'Budi Santoso', 'wilayah' => 'Sukamaju']);

    $this->get(route('dpt', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Sukamaju');
});

it('shows the total and per wilayah summary', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Dpt::factory()->count(2)->for($project)->create(['wilayah' => 'A']);
    Dpt::factory()->for($project)->create(['wilayah' => 'B']);
    Dpt::factory()->create();

    Livewire::test(DPT_SUMMARY, ['project' => $project])
        ->assertSee('Total DPT')
        ->assertSee('Desa');

    $summary = app(DptService::class)->summary($project);
    expect($summary['total'])->toBe(3)
        ->and($summary['perWilayah']->all())->toBe([['nama' => 'A', 'jumlah' => 2], ['nama' => 'B', 'jumlah' => 1]]);
});

it('adds a dpt row manually', function () {
    $project = Project::factory()->create();

    Livewire::test(DPT_ADD, ['project' => $project])
        ->set('name', ' Siti ')
        ->set('nik', nikFor('1992-03-09', true))
        ->set('birth_date', '1992-03-09')
        ->set('gender', 'Perempuan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('dpt-changed')
        ->assertSet('name', '');

    expect(Dpt::first())
        ->name->toBe('Siti')
        ->project_id->toBe($project->id)
        ->gender->toBe('Perempuan');
});

it('validates manual input', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    $nik = nikFor('1990-05-15');
    Dpt::factory()->for($project)->create(['nik' => $nik]);

    Livewire::test(DPT_ADD, ['project' => $project])
        ->set('nik', '123')
        ->call('save')
        ->assertHasErrors(['name', 'nik', 'birth_date', 'wilayah']);

    Livewire::test(DPT_ADD, ['project' => $project])
        ->set('name', 'Dobel')->set('nik', $nik)->set('birth_date', '1990-05-15')->set('wilayah', 'A')
        ->call('save')
        ->assertHasErrors(['nik']);

    Livewire::test(DPT_ADD, ['project' => $project])
        ->set('name', 'Beda Tanggal')->set('nik', nikFor('1990-05-15', false, '0002'))->set('birth_date', '1991-05-15')->set('wilayah', 'A')
        ->call('save')
        ->assertHasNoErrors();
});

it('allows the same nik in another project', function () {
    $nik = nikFor('1990-05-15');
    Dpt::factory()->create(['nik' => $nik]);
    $project = Project::factory()->create();

    Livewire::test(DPT_ADD, ['project' => $project])
        ->set('name', 'Budi')->set('nik', $nik)->set('birth_date', '1990-05-15')
        ->call('save')
        ->assertHasNoErrors();
});

it('searches, filters and paginates the table', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Dpt::factory()->count(12)->for($project)->create(['wilayah' => 'A']);
    Dpt::factory()->for($project)->create(['name' => 'Zainal Unik', 'wilayah' => 'B']);

    $component = Livewire::test(DPT_LIST, ['project' => $project]);
    expect($component->instance()->rows->total())->toBe(13)
        ->and($component->instance()->rows)->toHaveCount(10);

    $component->set('search', 'Zainal');
    expect($component->instance()->rows->total())->toBe(1);

    $component->set('search', '')->set('filterWilayah', 'A');
    expect($component->instance()->rows->total())->toBe(12);
});

it('updates a dpt row', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    $dpt = Dpt::factory()->for($project)->create(['nik' => nikFor('1990-05-15'), 'birth_date' => '1990-05-15']);

    Livewire::test(DPT_LIST, ['project' => $project])
        ->call('update', ['id' => $dpt->id, 'name' => 'Nama Baru', 'birth_date' => '1990-05-15', 'wilayah' => 'Baru'])
        ->assertHasNoErrors()
        ->assertDispatched('dpt-saved');

    expect($dpt->fresh())->name->toBe('Nama Baru')->wilayah->toBe('Baru');
});

it('accepts an update whose birth date differs from the nik', function () {
    $project = Project::factory()->create();
    $dpt = Dpt::factory()->for($project)->create(['name' => 'Asli', 'nik' => nikFor('1990-05-15'), 'birth_date' => '1990-05-15']);

    Livewire::test(DPT_LIST, ['project' => $project])
        ->call('update', ['id' => $dpt->id, 'name' => 'X', 'birth_date' => '1991-01-01'])
        ->assertHasNoErrors();

    expect($dpt->fresh())->name->toBe('X')->birth_date->format('Y-m-d')->toBe('1991-01-01');
});

it('deletes a dpt row', function () {
    $project = Project::factory()->create();
    $dpt = Dpt::factory()->for($project)->create();

    Livewire::test(DPT_LIST, ['project' => $project])->call('delete', $dpt->id);

    expect(Dpt::find($dpt->id))->toBeNull();
});

it('deletes the selected dpt rows only', function () {
    $project = Project::factory()->create();
    $rows = Dpt::factory()->for($project)->count(4)->create();
    $foreign = Dpt::factory()->create();

    Livewire::test(DPT_LIST, ['project' => $project])
        ->set('selected', [(string) $rows[0]->id, (string) $rows[1]->id, (string) $foreign->id])
        ->call('deleteSelected')
        ->assertSet('selected', []);

    expect(Dpt::whereKey($rows->pluck('id'))->count())->toBe(2)
        ->and(Dpt::find($foreign->id))->not->toBeNull();
});

it('selects a whole page and then every row matching the filter', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Dpt::factory()->for($project)->count(15)->create(['wilayah' => 'A']);
    $kept = Dpt::factory()->for($project)->count(3)->create(['wilayah' => 'B']);
    $other = Dpt::factory()->create(['wilayah' => 'A']);

    $component = Livewire::test(DPT_LIST, ['project' => $project])
        ->set('filterWilayah', 'A')
        ->call('togglePage');

    expect($component->get('selected'))->toHaveCount(10);

    $component->call('togglePage')->assertSet('selected', []);

    $component->call('togglePage')
        ->call('selectAllFiltered')
        ->assertSet('selectAllMatching', true)
        ->call('deleteSelected');

    expect($project->Dpt()->where('wilayah', 'A')->count())->toBe(0)
        ->and($project->Dpt()->count())->toBe(3)
        ->and(Dpt::find($other->id))->not->toBeNull()
        ->and(Dpt::whereKey($kept->pluck('id'))->count())->toBe(3);
});

it('clears the selection when the filter changes', function () {
    $project = Project::factory()->create();
    $dpt = Dpt::factory()->for($project)->create();

    Livewire::test(DPT_LIST, ['project' => $project])
        ->set('selected', [(string) $dpt->id])
        ->set('search', 'abc')
        ->assertSet('selected', []);
});

it('cannot touch a dpt row from another project', function () {
    $project = Project::factory()->create();
    $other = Dpt::factory()->create();

    Livewire::test(DPT_LIST, ['project' => $project])->call('delete', $other->id)->assertNotFound();
    Livewire::test(DPT_LIST, ['project' => $project])
        ->call('update', ['id' => $other->id, 'name' => 'X', 'birth_date' => '1990-01-01'])
        ->assertNotFound();

    expect(Dpt::find($other->id))->not->toBeNull();
});

it('blocks add, update, delete and import when the dpt is locked', function () {
    $project = Project::factory()->dptLocked()->create();
    $dpt = Dpt::factory()->for($project)->create(['name' => 'Asli']);

    Livewire::test(DPT_ADD, ['project' => $project])
        ->set('name', 'Baru')->set('nik', nikFor('1990-05-15'))->set('birth_date', '1990-05-15')
        ->call('save')
        ->assertHasErrors('project');

    Livewire::test(DPT_LIST, ['project' => $project])
        ->call('update', ['id' => $dpt->id, 'name' => 'X', 'birth_date' => $dpt->birth_date->format('Y-m-d')])
        ->assertHasErrors('project');
    Livewire::test(DPT_LIST, ['project' => $project])->call('delete', $dpt->id)->assertHasErrors('project');
    Livewire::test(DPT_LIST, ['project' => $project])
        ->set('selected', [(string) $dpt->id])
        ->call('deleteSelected')
        ->assertHasErrors('project');
    Livewire::test(DPT_LIST, ['project' => $project])
        ->call('selectAllFiltered')
        ->call('deleteSelected')
        ->assertHasErrors('project');

    Livewire::test(DPT_IMPORT, ['project' => $project])
        ->set('file', xlsxUpload([['Nama', 'NIK', 'Tanggal lahir'], ['Budi', nikFor('1990-05-15'), '15/05/1990']]))
        ->call('import')
        ->assertHasErrors('project');

    expect(Dpt::count())->toBe(1)->and($dpt->fresh()->name)->toBe('Asli');
});

it('previews an excel file with automatic column mapping and reports problem rows', function () {
    $project = Project::factory()->withWilayah('Desa')->create();
    Dpt::factory()->for($project)->create(['nik' => nikFor('1985-01-01')]);

    $component = Livewire::test(DPT_IMPORT, ['project' => $project])
        ->set('file', xlsxUpload([
            ['Nama', 'NIK', 'Tanggal Lahir', 'Desa', 'Jenis Kelamin'],
            ['Budi', nikFor('1990-05-15'), '15/05/1990', 'A', 'L'],
            ['Siti', nikFor('1992-03-09', true), '09-03-1992', 'B', 'P'],
            ['', nikFor('1990-05-15', false, '0002'), '15/05/1990', 'A', ''],
            ['NIK Pendek', '123', '15/05/1990', 'A', ''],
            ['Sudah Ada', nikFor('1985-01-01'), '01/01/1985', 'A', ''],
            ['Dobel', nikFor('1990-05-15'), '15/05/1990', 'A', ''],
            ['Salah Tgl', nikFor('1990-05-15', false, '0003'), '16/05/1990', 'A', ''],
        ]))
        ->assertHasNoErrors()
        ->assertSet('mapping', [0 => 'name', 1 => 'nik', 2 => 'birth_date', 3 => 'wilayah', 4 => 'gender']);

    $analysis = $component->instance()->analysis;

    expect($analysis['valid'])->toHaveCount(3)
        ->and($analysis['problems'])->toHaveCount(4)
        ->and(collect($analysis['problems'])->pluck('row')->all())->toBe([4, 5, 6, 7])
        ->and($analysis['valid'][1])->toMatchArray(['name' => 'Siti', 'birth_date' => '1992-03-09', 'gender' => 'Perempuan']);
});

it('imports only the valid rows', function () {
    $project = Project::factory()->create();

    Livewire::test(DPT_IMPORT, ['project' => $project])
        ->set('file', xlsxUpload([
            ['Nama', 'NIK', 'Tanggal lahir'],
            ['Budi', nikFor('1990-05-15'), '15/05/1990'],
            ['Salah', '123', '15/05/1990'],
        ]))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importedCount', 1)
        ->assertSet('file', null)
        ->assertDispatched('dpt-changed');

    expect(Dpt::count())->toBe(1)
        ->and(Dpt::first())->name->toBe('Budi')->project_id->toBe($project->id);
});

it('requires required columns to be mapped before importing', function () {
    $project = Project::factory()->create();

    $component = Livewire::test(DPT_IMPORT, ['project' => $project])
        ->set('file', xlsxUpload([['Kolom A', 'Kolom B'], ['Budi', nikFor('1990-05-15')]]));

    expect($component->instance()->analysis['missingFields'])->toBe(['Nama', 'NIK', 'Tanggal lahir']);

    $component->call('import')->assertHasErrors('mapping');
    expect(Dpt::count())->toBe(0);
});

it('rejects files that are not xlsx', function () {
    $project = Project::factory()->create();

    Livewire::test(DPT_IMPORT, ['project' => $project])
        ->set('file', UploadedFile::fake()->create('dpt.pdf', 10, 'application/pdf'))
        ->assertHasErrors('file')
        ->assertSet('file', null);
});

it('downloads the excel template', function () {
    $project = Project::factory()->withWilayah('Kelurahan')->create();

    $response = $this->get(route('dpt.template', ['project' => $project->code_project]))->assertOk();

    expect($response->baseResponse->headers->get('content-disposition'))->toContain('template-dpt.xlsx');
});

it('tells the user to map the wilayah column only when the project uses wilayah', function () {
    $upload = fn () => xlsxUpload([['Nama', 'NIK', 'Tanggal lahir'], ['Budi', nikFor('1990-05-15'), '15/05/1990']]);

    Livewire::test(DPT_IMPORT, ['project' => Project::factory()->withWilayah('Jaga')->create()])
        ->set('file', $upload())
        ->assertSee('Survei ini memakai wilayah');

    Livewire::test(DPT_IMPORT, ['project' => Project::factory()->create()])
        ->set('file', $upload())
        ->assertDontSee('Survei ini memakai wilayah');
});
