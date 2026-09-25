<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\PushSubscriptionController;
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

    Route::view('registros/novo', 'emotion-logs.create')->name('emotion-logs.create');
    Route::view('registros', 'emotion-logs.index')->name('emotion-logs.index');

    Route::view('relatorios', 'reports.index')->name('reports.index');
    Route::get('relatorios/pdf', [ExportController::class, 'pdf'])->name('reports.pdf');
    Route::get('relatorios/excel', [ExportController::class, 'excel'])->name('reports.excel');

    Route::view('lembretes', 'reminders.index')->name('reminders.index');
});

Route::middleware(['auth'])->group(function () {
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
});

require __DIR__.'/auth.php';
