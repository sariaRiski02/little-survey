<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

test('daftar proyek tampil di halaman', function () {
    $project = Project::factory()->create(['name' => 'Survei Kota Contoh']);

    $this->get(route('proyek.index'))
        ->assertOk()
        ->assertSee('Survei Kota Contoh')
        ->assertSee($project->code_project);
});

test('membuat proyek baru dengan kode unik otomatis', function () {
    Livewire::test('livewire-component.project')
        ->call('prepare')
        ->set('name', 'Pilkada Baru')
        ->set('description', 'Survei preferensi pemilih')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('project-saved');

    $project = Project::firstWhere('name', 'Pilkada Baru');

    expect($project)->not->toBeNull()
        ->and($project->description)->toBe('Survei preferensi pemilih')
        ->and($project->code_project)->toStartWith('PRJ-')
        ->and($project->is_locked)->toBeFalse();
});

test('membuat proyek gagal jika input kosong', function () {
    Livewire::test('livewire-component.project')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'description' => 'required']);

    expect(Project::count())->toBe(0);
});

test('mengubah proyek yang ada', function () {
    $project = Project::factory()->create();

    Livewire::test('livewire-component.project')
        ->call('prepare', $project->id)
        ->assertSet('name', $project->name)
        ->set('name', 'Nama Baru')
        ->set('description', 'Deskripsi baru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('project-saved');

    expect($project->fresh())
        ->name->toBe('Nama Baru')
        ->description->toBe('Deskripsi baru')
        ->code_project->toBe($project->code_project);
    expect(Project::count())->toBe(1);
});

test('mengubah proyek gagal jika nama kosong', function () {
    $project = Project::factory()->create();

    Livewire::test('livewire-component.project')
        ->call('prepare', $project->id)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect($project->fresh()->name)->toBe($project->name);
});

test('menghapus proyek', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    Livewire::test('livewire-component.project')
        ->call('prepare', $project->id)
        ->call('delete')
        ->assertDispatched('project-deleted');

    expect(Project::find($project->id))->toBeNull()
        ->and(Project::find($other->id))->not->toBeNull();
});
