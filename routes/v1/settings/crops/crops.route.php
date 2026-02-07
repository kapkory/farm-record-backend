<?php
$controller = \App\Http\Controllers\Api\v1\Settings\Crops\CropsController::class;
Route::post('/{uuid?}', [$controller, 'create'])->where(['uuid' => '[0-9]+']);
Route::get('/list', [$controller, 'listCrops']);
