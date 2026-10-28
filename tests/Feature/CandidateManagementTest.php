<?php

use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const LIST_COMPONENT = 'livewire-component.candidate.list-candidate';
const ORDER_COMPONENT = 'livewire-component.candidate.order-setting';

function candidatePayload(Candidate $candidate, array $overrides = []): array
{
    return array_merge([
        'id' => $candidate->id,
        'name' => 'Nama Baru',
        'number' => 5,
        'color' => '#112233',
        'picture' => $candidate->picture,
    ], $overrides);
}

it('updates a candidate', function () {
    $project = Project::factory()->create();
    $candidate = Candidate::factory()->for($project)->create(['number' => 1, 'picture' => null]);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('update', candidatePayload($candidate))
        ->assertHasNoErrors()
        ->assertDispatched('candidate-saved');

    expect($candidate->fresh())
        ->name->toBe('nama baru')
        ->number->toBe(5)
        ->color->toBe('#112233');
});

it('replaces the picture and removes the old file on update', function () {
    Storage::fake('public');
    Storage::disk('public')->put('pictures/old.jpg', 'old');
    $project = Project::factory()->create();
    $candidate = Candidate::factory()->for($project)->create(['picture' => 'pictures/old.jpg']);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->set('photo', UploadedFile::fake()->image('new.jpg'))
        ->call('update', candidatePayload($candidate))
        ->assertHasNoErrors();

    $newPicture = $candidate->fresh()->picture;
    expect($newPicture)->not->toBe('pictures/old.jpg');
    Storage::disk('public')->assertExists($newPicture);
    Storage::disk('public')->assertMissing('pictures/old.jpg');
});

it('keeps the old picture when no new photo is uploaded', function () {
    $project = Project::factory()->create();
    $candidate = Candidate::factory()->for($project)->create(['picture' => 'pictures/old.jpg']);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('update', candidatePayload($candidate, ['picture' => 'pictures/hacked.jpg']));

    expect($candidate->fresh()->picture)->toBe('pictures/old.jpg');
});

it('validates candidate update input', function () {
    $project = Project::factory()->create();
    Candidate::factory()->for($project)->create(['number' => 5]);
    $candidate = Candidate::factory()->for($project)->create(['number' => 1]);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('update', candidatePayload($candidate, ['name' => '', 'number' => 5, 'color' => 'red']))
        ->assertHasErrors(['name', 'number', 'color']);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('update', candidatePayload($candidate, ['number' => 1]))
        ->assertHasNoErrors();
});

it('cannot update a candidate from another project', function () {
    $project = Project::factory()->create();
    $other = Candidate::factory()->create(['name' => 'asli']);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('update', candidatePayload($other))
        ->assertNotFound();

    expect($other->fresh()->name)->toBe('asli');
});

it('deletes a candidate and its picture', function () {
    Storage::fake('public');
    Storage::disk('public')->put('pictures/old.jpg', 'old');
    $project = Project::factory()->create();
    $candidate = Candidate::factory()->for($project)->create(['picture' => 'pictures/old.jpg']);

    Livewire::test(LIST_COMPONENT, ['project' => $project])->call('delete', $candidate->id);

    expect(Candidate::find($candidate->id))->toBeNull();
    Storage::disk('public')->assertMissing('pictures/old.jpg');
});

it('cannot delete a candidate from another project', function () {
    $project = Project::factory()->create();
    $other = Candidate::factory()->create();

    Livewire::test(LIST_COMPONENT, ['project' => $project])->call('delete', $other->id)
        ->assertNotFound();

    expect(Candidate::find($other->id))->not->toBeNull();
});

it('blocks add, update and delete when the project is locked', function () {
    $project = Project::factory()->locked()->create();
    $candidate = Candidate::factory()->for($project)->create(['name' => 'asli']);

    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('update', candidatePayload($candidate))
        ->assertHasErrors('project');
    Livewire::test(LIST_COMPONENT, ['project' => $project])
        ->call('delete', $candidate->id)
        ->assertHasErrors('project');

    Livewire::test('livewire-component.candidate.add-candidate', ['project' => $project])
        ->set('name', 'Baru')
        ->call('save')
        ->assertHasErrors('project');

    expect($candidate->fresh()->name)->toBe('asli')
        ->and($project->candidate()->count())->toBe(1);
});

it('persists the random order setting', function () {
    $project = Project::factory()->create();

    Livewire::test(ORDER_COMPONENT, ['project' => $project])
        ->assertSet('randomize', false)
        ->call('toggle')
        ->assertSet('randomize', true);

    expect($project->fresh()->randomize_candidates)->toBeTrue();

    Livewire::test(ORDER_COMPONENT, ['project' => $project->fresh()])
        ->assertSet('randomize', true)
        ->call('toggle')
        ->assertSet('randomize', false);
});

it('blocks the random order setting when the project is locked', function () {
    $project = Project::factory()->locked()->create();

    Livewire::test(ORDER_COMPONENT, ['project' => $project])
        ->call('toggle')
        ->assertHasErrors('project');

    expect($project->fresh()->randomize_candidates)->toBeFalse();
});
