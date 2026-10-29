<?php

use App\Models\Project;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows the landing page and login form to guests', function () {
    $this->get(route('beranda'))->assertOk()->assertSee('lamdatech.netlify.app', false);
    $this->get(route('login'))->assertOk()->assertSee('Masuk');
});

it('redirects guests away from admin pages', function () {
    $project = Project::factory()->create();

    $this->get(route('proyek.index'))->assertRedirect(route('login'));
    $this->get(route('dashboard', ['project' => $project->code_project]))->assertRedirect(route('login'));
});

it('keeps the public survey result page open', function () {
    $project = Project::factory()->published()->create();

    $this->get(route('hasil', ['project' => $project->code_project]))->assertOk();
});

it('logs in with correct credentials', function () {
    $user = User::factory()->create(['password' => 'rahasia-banget']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'rahasia-banget'])
        ->assertRedirect(route('proyek.index'));

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'salah'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles repeated login attempts', function () {
    foreach (range(1, 5) as $ignored) {
        $this->post(route('login.store'), ['email' => 'a@b.test', 'password' => 'salah']);
    }

    $this->post(route('login.store'), ['email' => 'a@b.test', 'password' => 'salah'])->assertTooManyRequests();
});

it('sends logged in users away from the login page and lets them log out', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('login'))->assertRedirect(route('proyek.index'));
    $this->post(route('logout'))->assertRedirect(route('beranda'));

    $this->assertGuest();
});

it('does not expose registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

it('seeds exactly one admin user from configuration', function () {
    User::factory()->count(2)->create();
    config(['auth.admin' => ['name' => 'Saya', 'email' => 'saya@example.com', 'password' => 'sandi-rahasia']]);

    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    expect(User::count())->toBe(1);
    $this->post(route('login.store'), ['email' => 'saya@example.com', 'password' => 'sandi-rahasia'])
        ->assertRedirect(route('proyek.index'));
});
