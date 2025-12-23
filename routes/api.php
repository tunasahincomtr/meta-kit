<?php

use Illuminate\Support\Facades\Route;
use TunaSahincomtr\MetaKit\Http\Controllers\Api\MetaKitPageController;

$prefix = config('metakit.api_prefix', 'api/metakit');
$middleware = config('metakit.api_middleware', ['api', 'auth:sanctum']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('/pages', [MetaKitPageController::class, 'index']);
        Route::post('/pages', [MetaKitPageController::class, 'store']);
        Route::get('/pages/{page}', [MetaKitPageController::class, 'show']);
        Route::put('/pages/{page}', [MetaKitPageController::class, 'update']);
        Route::delete('/pages/{page}', [MetaKitPageController::class, 'destroy']);
        Route::post('/pages/quick-create', [MetaKitPageController::class, 'quickCreate']);
    });

