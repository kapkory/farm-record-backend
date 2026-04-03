<?php

$controller = \App\Http\Controllers\Api\v1\Settings\Animals\AnimalBreedsController::class;

Route::post('/', [$controller, 'create']);
Route::put('/{uuid}', [$controller, 'create']);
Route::get('/list', [$controller, 'listAnimalBreeds']);
Route::delete('/{uuid}', [$controller, 'delete']);

