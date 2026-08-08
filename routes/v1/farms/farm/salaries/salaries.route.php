<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\SalariesController::class;

// Wages are money — owner/manager only.
Route::middleware('finances')->group(function () use ($controller) {
    Route::get('/list/{farm_uuid}', [$controller, 'index']);
    Route::post('/', [$controller, 'store']);
});
