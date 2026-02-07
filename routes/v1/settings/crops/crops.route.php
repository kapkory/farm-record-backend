<?php
$controller = \App\Http\Controllers\Api\v1\Settings\Crops\CropsController::class;

// UUIDs are not numeric, so don't restrict to [0-9]+.
Route::post('/{uuid?}', [$controller, 'create'])->whereUuid('uuid');
Route::get('/list', [$controller, 'listCrops']);
Route::delete('/{uuid}', [$controller, 'delete'])->whereUuid('uuid');
