<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\AnimalEventsController::class;

Route::post('/', [$controller, 'store']);
Route::get('/list/{uuid}', [$controller, 'listEvents']);
Route::delete('/{uuid}', [$controller, 'destroy']);

