<?php

use Illuminate\Support\Facades\Route;
use TunaSahincomtr\MetaKit\Http\Controllers\Api\MetaKitAliasController;
use TunaSahincomtr\MetaKit\Http\Controllers\Api\MetaKitPageController;
use TunaSahincomtr\MetaKit\Http\Controllers\Api\MetaKitStatsController;

$prefix = config('metakit.api_prefix', 'api/metakit');
$publicMiddleware = config('metakit.api_public_middleware', ['api']);
$authMiddleware = config('metakit.api_auth_middleware', ['api', 'auth:sanctum']);

// Public routes (GET - Okuma işlemleri)
Route::prefix($prefix)
    ->middleware($publicMiddleware)
    ->group(function () {
        Route::get('/pages', [MetaKitPageController::class, 'index']);
        Route::get('/pages/{page}', [MetaKitPageController::class, 'show']);
        Route::get('/pages/export/csv', [MetaKitPageController::class, 'exportCsv'])->name('metakit.pages.export.csv');
        Route::get('/pages/export/json', [MetaKitPageController::class, 'exportJson'])->name('metakit.pages.export.json');
        Route::get('/aliases', [MetaKitAliasController::class, 'index']);
        Route::get('/aliases/{alias}', [MetaKitAliasController::class, 'show']);
        
        // Statistics routes
        Route::get('/stats/dashboard', [MetaKitStatsController::class, 'dashboard']);
        Route::get('/stats/missing-meta', [MetaKitStatsController::class, 'missingMeta']);
        Route::get('/stats/duplicates', [MetaKitStatsController::class, 'duplicates']);
    });

// Protected routes (POST/PUT/DELETE - Yazma işlemleri - Auth gerekli)
Route::prefix($prefix)
    ->middleware($authMiddleware)
    ->group(function () {
        Route::post('/pages', [MetaKitPageController::class, 'store']);
        Route::put('/pages/{page}', [MetaKitPageController::class, 'update']);
        Route::delete('/pages/{page}', [MetaKitPageController::class, 'destroy']);
        Route::post('/pages/quick-create', [MetaKitPageController::class, 'quickCreate']);
        Route::post('/pages/import/json', [MetaKitPageController::class, 'importJson']);
        Route::post('/pages/import/csv', [MetaKitPageController::class, 'importCsv']);
        
        Route::post('/aliases', [MetaKitAliasController::class, 'store']);
        Route::put('/aliases/{alias}', [MetaKitAliasController::class, 'update']);
        Route::delete('/aliases/{alias}', [MetaKitAliasController::class, 'destroy']);
    });

