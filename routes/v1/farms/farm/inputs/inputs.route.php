<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Inputs\FarmInputsController::class;

Route::get('/list/{farm_uuid?}', [$controller, 'index']);
Route::post('/', [$controller, 'store']);
Route::post('/{uuid}/applications', [$controller, 'apply']);
Route::delete('/applications/{uuid}', [$controller, 'reverseApplication']);
Route::get('/{uuid}', [$controller, 'show']);
Route::put('/{uuid}', [$controller, 'update']);
Route::delete('/{uuid}', [$controller, 'destroy']);
