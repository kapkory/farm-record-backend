<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\AnimalsController::class;

Route::post('/', [$controller, 'store']);
Route::get('/group/{group_uuid}', [$controller, 'listByGroup']);
Route::get('/standalone/{farm_uuid}', [$controller, 'listStandalone']);
Route::get('/{uuid}', [$controller, 'show']);
Route::put('/{uuid}', [$controller, 'update']);
Route::delete('/{uuid}', [$controller, 'destroy']);

