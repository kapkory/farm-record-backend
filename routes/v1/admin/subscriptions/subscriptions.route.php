<?php

use App\Http\Controllers\Api\v1\Admin\SubscriptionsController;

$controller = SubscriptionsController::class;

// Superadmin-only subscription oversight and manual billing actions.
Route::middleware('superadmin')->group(function () use ($controller) {
    Route::get('/', [$controller, 'index']);
    Route::get('/stats', [$controller, 'stats']);
    Route::get('/{uuid}', [$controller, 'show'])->whereUuid('uuid');
    Route::post('/assign/{farmerUuid}', [$controller, 'assign'])->whereUuid('farmerUuid');
    Route::post('/{uuid}/payments', [$controller, 'recordPayment'])->whereUuid('uuid');
    Route::post('/{uuid}/status', [$controller, 'updateStatus'])->whereUuid('uuid');
    Route::post('/{uuid}/cancel', [$controller, 'cancel'])->whereUuid('uuid');
});
