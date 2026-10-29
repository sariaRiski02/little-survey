<?php

use App\Models\Candidate;
use App\Models\Project;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

const QUESTION_ADD = 'livewire-component.question.add-question';
const QUESTION_LIST = 'livewire-component.question.list-question';

it('renders the pertanyaan page with questions from the database', function () {
    $project = Project::factory()->create();
    Question::factory()->for($project)->create(['text' => 'Apakah Anda sudah makan?', 'position' => 1]);

    $this->get(route('pertanyaan', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('Apakah Anda sudah makan?')
        ->assertSee('Daftar pertanyaan (1)')
        ->assertSee('± 1 menit');
});

it('estimates the duration on the server', function () {
    $service = app(QuestionService::class);

    expect($service->estimatedMinutes(0))->toBe(1)
        ->and($service->estimatedMinutes(5))->toBe(3)
        ->and($service->estimatedMinutes(10))->toBe(5);
});

it('adds a single choice question at the end of the list', function () {
    $project = Project::factory()->create();
    Question::factory()->for($project)->create(['position' => 1]);

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', '  Bagaimana kondisi jalan di desa Anda?  ')
        ->set('description', 'Pilih satu')
        ->set('type', 'tunggal')
        ->set('options', "Baik\n\n Cukup \nBuruk")
        ->set('has_refuse_option', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('question-changed')
        ->assertSet('text', '')
        ->assertSet('is_required', true);

    $question = $project->Question()->get()->last();

    expect($question)
        ->position->toBe(2)
        ->text->toBe('Bagaimana kondisi jalan di desa Anda?')
        ->options->toBe(['Baik', 'Cukup', 'Buruk'])
        ->has_refuse_option->toBeTrue();
});

it('stores no options for free text questions', function () {
    $project = Project::factory()->create();

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Apa harapan Anda?')
        ->set('type', 'isian')
        ->set('options', "A\nB")
        ->set('has_refuse_option', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($project->Question()->first())->options->toBeNull()->has_refuse_option->toBeFalse();
});

it('validates question input', function () {
    $project = Project::factory()->create();

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->call('save')
        ->assertHasErrors(['text', 'options']);

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Satu pilihan')->set('options', 'Hanya satu')
        ->call('save')
        ->assertHasErrors('options');

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Dobel')->set('options', "Ya\nya")
        ->call('save')
        ->assertHasErrors('options');

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Tipe salah')->set('type', 'xyz')
        ->call('save')
        ->assertHasErrors('type');

    expect($project->Question()->count())->toBe(0);
});

it('requires at least two candidates for a candidate question', function () {
    $project = Project::factory()->create();
    Candidate::factory()->for($project)->create(['name' => 'ahmad']);

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Siapa pilihan Anda?')->set('type', 'kandidat')
        ->call('save')
        ->assertHasErrors('type');

    Candidate::factory()->for($project)->create(['name' => 'budi']);

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Siapa pilihan Anda?')->set('type', 'kandidat')
        ->call('save')
        ->assertHasNoErrors();

    expect($project->Question()->first()->options)->toBeNull();
});

it('creates a branching question that points to an earlier question', function () {
    $project = Project::factory()->create();
    $parent = Question::factory()->for($project)->create(['options' => ['Ya', 'Tidak']]);

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Mengapa?')->set('type', 'isian')
        ->set('branch_question_id', (string) $parent->id)
        ->set('branch_answer', 'Tidak')
        ->call('save')
        ->assertHasNoErrors();

    expect($project->Question()->get()->last())
        ->branch_question_id->toBe($parent->id)
        ->branch_answer->toBe('Tidak');
});

it('validates branching rules', function () {
    $project = Project::factory()->create();
    $parent = Question::factory()->for($project)->create(['options' => ['Ya', 'Tidak']]);
    $textParent = Question::factory()->for($project)->create(['type' => 'isian', 'options' => null]);
    $foreign = Question::factory()->create();

    $try = fn (array $state) => tap(Livewire::test(QUESTION_ADD, ['project' => $project])->set('text', 'Cabang')->set('type', 'isian'), function ($component) use ($state) {
        foreach ($state as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('save');
    });

    $try(['branch_question_id' => (string) $parent->id, 'branch_answer' => ''])->assertHasErrors('branch_answer');
    $try(['branch_question_id' => (string) $parent->id, 'branch_answer' => 'Mungkin'])->assertHasErrors('branch_answer');
    $try(['branch_question_id' => (string) $textParent->id, 'branch_answer' => 'x'])->assertHasErrors('branch_question_id');
    $try(['branch_question_id' => (string) $foreign->id, 'branch_answer' => 'Ya'])->assertHasErrors('branch_question_id');
});

it('moves questions up and down', function () {
    $project = Project::factory()->create();
    [$a, $b, $c] = Question::factory()->count(3)->for($project)->create()->all();

    $component = Livewire::test(QUESTION_LIST, ['project' => $project]);
    $component->call('move', $c->id, 'up')->assertDispatched('question-changed');

    expect($project->Question()->pluck('id')->all())->toBe([$a->id, $c->id, $b->id]);

    $component->call('move', $a->id, 'up');
    $component->call('move', $b->id, 'down');
    expect($project->Question()->pluck('id')->all())->toBe([$a->id, $c->id, $b->id]);
    expect($project->Question()->pluck('position')->all())->toBe([1, 2, 3]);
});

it('does not allow moving a branching question before the question it depends on', function () {
    $project = Project::factory()->create();
    $parent = Question::factory()->for($project)->create(['options' => ['Ya', 'Tidak']]);
    $child = Question::factory()->for($project)->create(['branch_question_id' => $parent->id, 'branch_answer' => 'Ya']);

    Livewire::test(QUESTION_LIST, ['project' => $project])
        ->call('move', $child->id, 'up')
        ->assertHasErrors('project');
    Livewire::test(QUESTION_LIST, ['project' => $project])
        ->call('move', $parent->id, 'down')
        ->assertHasErrors('project');

    expect($project->Question()->pluck('id')->all())->toBe([$parent->id, $child->id]);
});

it('deletes a question, renumbers the rest and clears dependent branches', function () {
    $project = Project::factory()->create();
    $parent = Question::factory()->for($project)->create();
    $child = Question::factory()->for($project)->create(['branch_question_id' => $parent->id, 'branch_answer' => 'Ya']);
    $last = Question::factory()->for($project)->create();

    Livewire::test(QUESTION_LIST, ['project' => $project])
        ->call('delete', $parent->id)
        ->assertDispatched('question-saved');

    expect(Question::find($parent->id))->toBeNull()
        ->and($child->fresh())->branch_question_id->toBeNull()->branch_answer->toBeNull()->position->toBe(1)
        ->and($last->fresh()->position)->toBe(2);
});

it('cannot touch a question from another project', function () {
    $project = Project::factory()->create();
    $other = Question::factory()->create();

    Livewire::test(QUESTION_LIST, ['project' => $project])->call('delete', $other->id)->assertNotFound();
    Livewire::test(QUESTION_LIST, ['project' => $project])->call('move', $other->id, 'up')->assertNotFound();

    expect(Question::find($other->id))->not->toBeNull();
});

it('appends the template questions with their branch', function () {
    $project = Project::factory()->create();
    Question::factory()->for($project)->create();

    Livewire::test(QUESTION_LIST, ['project' => $project])
        ->call('applyTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('question-saved');

    $questions = $project->Question()->get();

    expect($questions)->toHaveCount(1 + count(QuestionService::TEMPLATE))
        ->and($questions->pluck('position')->all())->toBe(range(1, $questions->count()))
        ->and($questions[2])->branch_question_id->toBe($questions[1]->id)->branch_answer->toBe('Ya');
});

it('blocks every change when the questions are locked', function () {
    $project = Project::factory()->questionsLocked()->create();
    $question = Question::factory()->for($project)->create(['text' => 'Asli']);
    $second = Question::factory()->for($project)->create();

    Livewire::test(QUESTION_ADD, ['project' => $project])
        ->set('text', 'Baru')->set('options', "A\nB")
        ->call('save')
        ->assertHasErrors('project');

    $list = Livewire::test(QUESTION_LIST, ['project' => $project]);
    $list->call('move', $second->id, 'up')->assertHasErrors('project');
    $list->call('delete', $question->id)->assertHasErrors('project');
    $list->call('applyTemplate')->assertHasErrors('project');

    expect($project->Question()->count())->toBe(2)
        ->and($project->Question()->first()->id)->toBe($question->id);
});

it('shows candidate names in the respondent preview', function () {
    $project = Project::factory()->create();
    Candidate::factory()->for($project)->create(['name' => 'ahmad santoso', 'number' => 1]);
    Candidate::factory()->for($project)->create(['name' => 'budi rahardjo', 'number' => 2]);
    Question::factory()->for($project)->create(['type' => 'kandidat', 'options' => null, 'has_refuse_option' => true]);

    Livewire::test(QUESTION_LIST, ['project' => $project])
        ->assertSee('Ahmad Santoso')
        ->assertSee('Budi Rahardjo')
        ->assertSee('Tidak mau menjawab');
});
