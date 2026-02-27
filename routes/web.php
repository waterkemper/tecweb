<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/attachments/{comment}/{index}', [TicketController::class, 'attachment'])
        ->name('tickets.attachment')
        ->whereNumber(['comment', 'index']);
    Route::post('/tickets/{ticket}/refresh-ai', [TicketController::class, 'refreshAi'])->name('tickets.refresh-ai');
    Route::post('/tickets/{ticket}/apply-tags', [TicketController::class, 'applySuggestedTags'])->name('tickets.apply-tags');
    Route::post('/tickets/{ticket}/internal-effort', [TicketController::class, 'updateInternalEffort'])->name('tickets.internal-effort');
    Route::post('/tickets/reorder', [TicketController::class, 'reorder'])->name('tickets.reorder');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/test-zendesk', [SettingsController::class, 'testZendesk'])->name('settings.test');
});
