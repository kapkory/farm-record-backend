<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Animals\AnimalWeightsController::class;

Route::get('/list/{uuid}', [$controller, 'listWeights']);
Route::post('/', [$controller, 'store']);
Route::delete('/{uuid}', [$controller, 'destroy']);
