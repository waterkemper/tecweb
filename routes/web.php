<?php

use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
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
    Route::post('/tickets/{ticket}/tags', [TicketController::class, 'updateTags'])->name('tickets.tags.update');
    Route::post('/tickets/{ticket}/tags/sync', [TicketController::class, 'syncTags'])->name('tickets.tags.sync');
    Route::post('/tickets/{ticket}/internal-effort', [TicketController::class, 'updateInternalEffort'])->name('tickets.internal-effort');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'storeComment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status.update');
    Route::post('/tickets/{ticket}/pending-action', [TicketController::class, 'updatePendingAction'])->name('tickets.pending-action.update');
    Route::post('/tickets/reorder', [TicketController::class, 'reorder'])->name('tickets.reorder');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.organizations.index'))->name('index');
        Route::get('/organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/{organization}/users', [AdminOrganizationController::class, 'users'])->name('organizations.users');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    });
});
