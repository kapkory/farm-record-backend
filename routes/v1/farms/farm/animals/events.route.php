<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Animals\AnimalEventsController::class;

Route::post('/', [$controller, 'store']);
Route::get('/list/{uuid}', [$controller, 'listEvents']);
Route::delete('/{uuid}', [$controller, 'destroy']);

