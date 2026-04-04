<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Animals\AnimalGroupsController::class;

Route::post('/', [$controller, 'storeAnimalGroup']);
Route::get('/list/{farm_uuid?}', [$controller, 'listAnimalGroups']);
Route::get('/{uuid}', [$controller, 'show']);
Route::put('/{uuid}', [$controller, 'update']);
Route::delete('/{uuid}', [$controller, 'destroy']);

