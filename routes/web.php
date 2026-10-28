<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DptController;
use App\Http\Controllers\KandidatController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PertanyaanController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\RespondenController;
use App\Http\Controllers\StatistikController;
use App\Http\Middleware\SetCurrentProjectMiddleware;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/proyek');
Route::get('/proyek', [ProjectsController::class, 'index'])->name('proyek.index');

Route::prefix('{project:code_project}')
        ->middleware([SetCurrentProjectMiddleware::class])
        ->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('/kandidat', [KandidatController::class, 'index'])->name('kandidat');
            Route::get('/dpt', [DptController::class, 'index'])->name('dpt');
            Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik');
            Route::get('/pertanyaan', [PertanyaanController::class, 'index'])->name('pertanyaan');
            Route::get('/responden', [RespondenController::class, 'index'])->name('responden');
            Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
});


