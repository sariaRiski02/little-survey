<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DptController;
use App\Http\Controllers\HasilController;
use App\Http\Controllers\JawabanController;
use App\Http\Controllers\KandidatController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PertanyaanController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\RespondenController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\SurveiController;
use App\Http\Middleware\SetCurrentProjectMiddleware;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.landing')->name('beranda');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::get('/hasil/{project:code_project}', [HasilController::class, 'show'])->middleware('throttle:60,1')->name('hasil');
Route::get('/s/{token}', [SurveiController::class, 'show'])->middleware('throttle:60,1')->name('survei');

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/proyek', [ProjectsController::class, 'index'])->name('proyek.index');
});

Route::prefix('{project:code_project}')
    ->middleware(['auth', SetCurrentProjectMiddleware::class])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/kandidat', [KandidatController::class, 'index'])->name('kandidat');
        Route::get('/dpt', [DptController::class, 'index'])->name('dpt');
        Route::get('/dpt/template', [DptController::class, 'template'])->name('dpt.template');
        Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik');
        Route::get('/pertanyaan', [PertanyaanController::class, 'index'])->name('pertanyaan');
        Route::get('/responden', [RespondenController::class, 'index'])->name('responden');
        Route::get('/responden/template', [RespondenController::class, 'template'])->name('responden.template');
        Route::get('/responden/ekspor', [RespondenController::class, 'export'])->name('responden.export');
        Route::get('/jawaban', [JawabanController::class, 'index'])->name('jawaban');
        Route::get('/jawaban/ekspor', [JawabanController::class, 'export'])->name('jawaban.export');
        Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
    });
