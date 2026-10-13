<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShellController::class, 'dashboard'])->name('dashboard');
Route::get('/shell', [ShellController::class, 'dashboard'])->name('shell.dashboard');

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update');
