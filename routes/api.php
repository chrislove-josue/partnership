<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/auth/history', [AuthController::class, 'history']);

    Route::patch('/profile', [ProfileController::class, 'update']);

    Route::get('/churches', [DataController::class, 'churches']);
    Route::post('/churches', [ManagementController::class, 'storeChurch']);
    Route::patch('/churches/{church}', [ManagementController::class, 'updateChurch']);
    Route::post('/churches/{church}/deactivate', [ManagementController::class, 'deactivateChurch']);
    Route::post('/churches/{church}/activate', [ManagementController::class, 'activateChurch']);
    Route::delete('/churches/{church}', [ManagementController::class, 'destroyChurch']);

    Route::get('/cellules', [DataController::class, 'cellules']);
    Route::post('/cellules', [ManagementController::class, 'storeCellule']);
    Route::patch('/cellules/{cellule}', [ManagementController::class, 'updateCellule']);
    Route::delete('/cellules/{cellule}', [ManagementController::class, 'destroyCellule']);

    Route::get('/partners', [DataController::class, 'partners']);
    Route::post('/partners', [ManagementController::class, 'storePartner']);
    Route::post('/partners/import', [ImportController::class, 'importPartners']);
    Route::get('/partners/template', [ImportController::class, 'partnerTemplate']);
    Route::patch('/partners/{partner}', [ManagementController::class, 'updatePartner']);
    Route::delete('/partners/{partner}', [ManagementController::class, 'destroyPartner']);

    Route::get('/categories', [DataController::class, 'categories']);
    Route::post('/categories', [ManagementController::class, 'storeCategory']);
    Route::patch('/categories/{category}', [ManagementController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [ManagementController::class, 'destroyCategory']);

    Route::get('/donations', [DataController::class, 'donations']);
    Route::post('/donations', [DataController::class, 'storeDonation']);
    Route::post('/donations/import', [ImportController::class, 'importDonations']);
    Route::get('/donations/template', [ImportController::class, 'donationTemplate']);
    Route::patch('/donations/{donation}', [DataController::class, 'updateDonation']);
    Route::delete('/donations/{donation}', [DataController::class, 'destroyDonation']);

    Route::get('/users', [ManagementController::class, 'users']);
    Route::post('/users', [ManagementController::class, 'storeUser']);
    Route::patch('/users/{target}', [ManagementController::class, 'updateUser']);
    Route::delete('/users/{target}', [ManagementController::class, 'destroyUser']);
    Route::get('/roles', [ManagementController::class, 'roles']);

    Route::get('/reports/partnership', [ReportController::class, 'show']);
    Route::get('/reports/partnership/recipients', [ReportController::class, 'recipients']);
    Route::get('/reports/partnership/export', [ReportController::class, 'export']);
    Route::post('/reports/partnership/send', [ReportController::class, 'send']);

    Route::get('/settings/api-url', [SettingsController::class, 'apiUrl']);

    Route::get('/sync/deletions', [SyncController::class, 'deletions']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
