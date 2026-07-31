<?php

use App\Http\Controllers\Api\v1\Admin\PlansController;

$controller = PlansController::class;

// Superadmin-only plan catalogue management.
Route::middleware('superadmin')->group(function () use ($controller) {
    Route::get('/', [$controller, 'index']);
    Route::post('/', [$controller, 'store']);
    Route::put('/{uuid}', [$controller, 'update'])->whereUuid('uuid');
    Route::delete('/{uuid}', [$controller, 'destroy'])->whereUuid('uuid');
});
