<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::patch('/profile', [ProfileController::class, 'update']);

    Route::get('/churches', [DataController::class, 'churches']);
    Route::get('/partners', [DataController::class, 'partners']);
    Route::get('/cellules', [DataController::class, 'cellules']);
    Route::get('/categories', [DataController::class, 'categories']);
    Route::get('/donations', [DataController::class, 'donations']);
    Route::post('/donations', [DataController::class, 'storeDonation']);

    Route::get('/reports/partnership', [ReportController::class, 'show']);
    Route::get('/reports/partnership/recipients', [ReportController::class, 'recipients']);
    Route::post('/reports/partnership/send', [ReportController::class, 'send']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
