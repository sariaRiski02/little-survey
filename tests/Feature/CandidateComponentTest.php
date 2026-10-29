<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders the candidate form through Livewire without loading Alpine twice', function () {
    $project = Project::factory()->create();

    $this->get(route('kandidat', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('method="POST"', false)
        ->assertSee('wire:submit="save"', false)
        ->assertDontSee('cdn.jsdelivr.net/npm/alpinejs');
});
