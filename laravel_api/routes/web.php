<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'index'])->name('portal');
Route::get('/reset-password', [PortalController::class, 'resetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [PortalController::class, 'resetPassword'])->name('password.update');
