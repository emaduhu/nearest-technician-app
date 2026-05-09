<?php

use App\Http\Controllers\Api\TechnicianApiController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [TechnicianApiController::class, 'health']);
Route::get('/portal/overview', [TechnicianApiController::class, 'portalOverview']);

Route::post('/register', [TechnicianApiController::class, 'register']);
Route::post('/login', [TechnicianApiController::class, 'login']);
Route::post('/forgot-password', [TechnicianApiController::class, 'forgotPassword']);
Route::post('/reset-password', [TechnicianApiController::class, 'resetPassword']);
Route::get('/technicians/search', [TechnicianApiController::class, 'searchTechnicians']);
Route::patch('/users/{user}/location', [TechnicianApiController::class, 'updateUserLocation']);
Route::patch('/technicians/{technician}/location', [TechnicianApiController::class, 'updateTechnicianLocation']);
Route::patch('/technicians/{technician}/availability', [TechnicianApiController::class, 'updateAvailability']);
Route::post('/requests', [TechnicianApiController::class, 'createRequest']);
Route::patch('/requests/{serviceRequest}/respond', [TechnicianApiController::class, 'respondToRequest']);
Route::get('/requests/history', [TechnicianApiController::class, 'requestHistory']);

Route::post('/request', [TechnicianApiController::class, 'createRequest']);
Route::post('/technician/login', [TechnicianApiController::class, 'legacyTechnicianLogin']);
Route::get('/technician/nearest', [TechnicianApiController::class, 'legacyNearestTechnician']);
