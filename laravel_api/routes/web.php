<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'home'])->name('portal');
Route::get('/login', [PortalController::class, 'loginForm'])->name('login');
Route::post('/login', [PortalController::class, 'login'])->name('login.store');
Route::post('/logout', [PortalController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dispatch', [PortalController::class, 'dispatch'])->name('dispatch');
    Route::get('/users', [PortalController::class, 'users'])->name('users.index');
    Route::post('/users', [PortalController::class, 'storeUser'])->name('users.store');
    Route::put('/users/{user}', [PortalController::class, 'updateUser'])->name('users.update');
    Route::delete('/users/{user}', [PortalController::class, 'destroyUser'])->name('users.destroy');
    Route::patch('/technicians/{technician}/availability', [PortalController::class, 'updateTechnicianAvailability'])->name('technicians.availability');
});

Route::get('/reset-password', [PortalController::class, 'resetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [PortalController::class, 'resetPassword'])->name('password.update');
