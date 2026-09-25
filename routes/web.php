<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::view('meus-dados', 'patient')
    ->middleware(['auth'])
    ->name('patient.edit');

// Rotas do domínio do app (dashboard, registro emocional, histórico, relatórios...)
// exigem o onboarding completo do paciente — ver docs/05.
Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
