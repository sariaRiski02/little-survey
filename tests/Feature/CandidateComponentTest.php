<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('renders the candidate form through Livewire without loading Alpine twice', function () {
    $project = Project::factory()->create();

    $this->get(route('kandidat', ['project' => $project->code_project]))
        ->assertOk()
        ->assertSee('method="POST"', false)
        ->assertSee('wire:submit="test"', false)
        ->assertDontSee('cdn.jsdelivr.net/npm/alpinejs');
});
