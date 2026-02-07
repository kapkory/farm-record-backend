<?php
$controller = \App\Http\Controllers\Api\v1\Settings\Crops\CropTypesController::class;
Route::post('/{uuid?}', [$controller, 'create']);
Route::get('/list', [$controller, 'listCropTypes']);
