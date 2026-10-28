<?php

use App\Models\Candidate;
use App\Models\Project;
use App\Models\Question;
use App\Models\Respondent;
use App\Models\RespondentAnswer;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const SURVEY_FORM = 'livewire-component.survey.fill-survey';

function surveyFixture(): array
{
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->create(['name' => 'Budi']);
    $q1 = Question::factory()->for($project)->create(['text' => 'Apakah Anda akan memilih?', 'options' => ['Ya', 'Tidak']]);
    $q2 = Question::factory()->for($project)->create([
        'text' => 'Mengapa Anda memilih?', 'type' => 'isian', 'options' => null, 'is_required' => false,
        'branch_question_id' => $q1->id, 'branch_answer' => 'Ya',
    ]);

    return [$project, $respondent, $q1, $q2];
}

it('opens the survey page by token only', function () {
    [, $respondent] = surveyFixture();

    $this->get(route('survei', ['token' => $respondent->token]))
        ->assertOk()
        ->assertSee('Halo Budi')
        ->assertSee('Apakah Anda akan memilih?')
        ->assertSee('noindex', false);

    $this->get(route('survei', ['token' => 'tidak-ada']))->assertNotFound();
});

it('shows a branching question only after its trigger answer', function () {
    [, $respondent, $q1] = surveyFixture();

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->assertDontSee('Mengapa Anda memilih?')
        ->set('answers.'.$q1->id, 'Tidak')
        ->assertDontSee('Mengapa Anda memilih?')
        ->set('answers.'.$q1->id, 'Ya')
        ->assertSee('Mengapa Anda memilih?');
});

it('stores the answers and marks the respondent as filled', function () {
    [$project, $respondent, $q1, $q2] = surveyFixture();

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$q1->id, 'Ya')
        ->set('answers.'.$q2->id, 'Karena peduli')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('done', true)
        ->assertSee('Terima kasih');

    $respondent->refresh();
    expect($respondent->status())->toBe('mengisi')
        ->and($respondent->link_sent_at)->not->toBeNull()
        ->and(RespondentAnswer::where('respondent_id', $respondent->id)->count())->toBe(2)
        ->and(RespondentAnswer::where('question_id', $q2->id)->first()->values)->toBe(['Karena peduli'])
        ->and($project->fresh())->is_locked->toBeTrue()->is_questions_locked->toBeTrue();
});

it('ignores answers to questions hidden by branching', function () {
    [, $respondent, $q1, $q2] = surveyFixture();

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$q1->id, 'Tidak')
        ->set('answers.'.$q2->id, 'Harusnya diabaikan')
        ->call('submit')
        ->assertHasNoErrors();

    expect(RespondentAnswer::where('question_id', $q2->id)->exists())->toBeFalse();
});

it('requires answers to required questions and rejects invalid choices', function () {
    [, $respondent, $q1] = surveyFixture();

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->call('submit')
        ->assertHasErrors(['answers.'.$q1->id]);

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$q1->id, 'Mungkin')
        ->call('submit')
        ->assertHasErrors(['answers.'.$q1->id]);

    expect($respondent->fresh()->filled_at)->toBeNull()->and(RespondentAnswer::count())->toBe(0);
});

it('handles multiple choice, refusal and optional questions', function () {
    $project = Project::factory()->create();
    $respondent = Respondent::factory()->for($project)->create();
    $multi = Question::factory()->for($project)->create(['type' => 'ganda', 'options' => ['A', 'B', 'C'], 'has_refuse_option' => true]);
    $single = Question::factory()->for($project)->create(['options' => ['Ya', 'Tidak'], 'has_refuse_option' => true]);
    $optional = Question::factory()->for($project)->create(['type' => 'isian', 'options' => null, 'is_required' => false]);
    $refuse = SurveyService::REFUSE;

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$multi->id, ['A', $refuse])
        ->set('answers.'.$single->id, $refuse)
        ->call('submit')
        ->assertHasErrors(['answers.'.$multi->id]);

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$multi->id, ['A', 'C'])
        ->set('answers.'.$single->id, $refuse)
        ->call('submit')
        ->assertHasNoErrors();

    expect(RespondentAnswer::where('question_id', $multi->id)->first()->values)->toBe(['A', 'C'])
        ->and(RespondentAnswer::where('question_id', $single->id)->first())->is_refused->toBeTrue()->values->toBe([])
        ->and(RespondentAnswer::where('question_id', $optional->id)->exists())->toBeFalse();
});

it('does not accept a second submission', function () {
    [, $respondent, $q1] = surveyFixture();
    $service = app(SurveyService::class);
    $service->submit($respondent, [$q1->id => 'Ya']);

    expect(fn () => $service->submit($respondent, [$q1->id => 'Tidak']))->toThrow(ValidationException::class);
    expect(RespondentAnswer::where('question_id', $q1->id)->sole()->values)->toBe(['Ya']);

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent->fresh()])->assertSet('done', true)->assertSee('Terima kasih');
});

it('validates candidate answers against the project candidates and shuffles per respondent', function () {
    $project = Project::factory()->create(['randomize_candidates' => true]);
    foreach (['ahmad', 'budi', 'citra', 'dewi', 'eko', 'fitri'] as $i => $name) {
        Candidate::factory()->for($project)->create(['name' => $name, 'number' => $i + 1]);
    }
    $question = Question::factory()->for($project)->create(['type' => 'kandidat', 'options' => null]);
    $respondent = Respondent::factory()->for($project)->create();
    $service = app(SurveyService::class);

    $first = $service->choices($respondent, $question);

    expect($first)->toEqualCanonicalizing(['Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri'])
        ->and($service->choices($respondent, $question))->toBe($first);

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$question->id, 'Orang Asing')
        ->call('submit')
        ->assertHasErrors(['answers.'.$question->id]);

    Livewire::test(SURVEY_FORM, ['respondent' => $respondent])
        ->set('answers.'.$question->id, 'Budi')
        ->call('submit')
        ->assertHasNoErrors();

    expect(RespondentAnswer::sole()->values)->toBe(['Budi']);
});
