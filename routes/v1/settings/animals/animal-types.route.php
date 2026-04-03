<?php

$controller = \App\Http\Controllers\Api\v1\Settings\Animals\AnimalTypesController::class;

Route::post('/', [$controller, 'create']);
Route::put('/{uuid}', [$controller, 'create']);
Route::get('/list', [$controller, 'listAnimalTypes']);
Route::delete('/{uuid}', [$controller, 'delete']);

