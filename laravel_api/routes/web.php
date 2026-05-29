<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'home'])->name('portal');
Route::get('/login', [PortalController::class, 'loginForm'])->name('login');
Route::post('/login', [PortalController::class, 'login'])->name('login.store');
Route::post('/logout', [PortalController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dispatch', [PortalController::class, 'dispatch'])->name('dispatch');
    Route::get('/technician', [PortalController::class, 'technicianDashboard'])->name('technician.dashboard');
    Route::patch('/technician/availability', [PortalController::class, 'updateOwnAvailability'])->name('technician.availability');
    Route::get('/users', [PortalController::class, 'users'])->name('users.index');
    Route::post('/users', [PortalController::class, 'storeUser'])->name('users.store');
    Route::put('/users/{user}', [PortalController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [PortalController::class, 'destroyUser'])->name('users.destroy');
    Route::patch('/users/{user}/block', [PortalController::class, 'toggleUserBlock'])->name('users.block');
    Route::patch('/technicians/{technician}/availability', [PortalController::class, 'updateTechnicianAvailability'])->name('technicians.availability');
    Route::post('/notifications/test', [PortalController::class, 'sendTestNotification'])->name('notifications.test');
    Route::post('/notifications/warning', [PortalController::class, 'sendWarningNotification'])->name('notifications.warning');
    Route::post('/notifications/news', [PortalController::class, 'sendNewsNotification'])->name('notifications.news');
});

Route::get('/reset-password', [PortalController::class, 'resetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [PortalController::class, 'resetPassword'])->name('password.update');
