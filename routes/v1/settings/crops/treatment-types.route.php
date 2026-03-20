<?php

$controller = \App\Http\Controllers\Api\v1\Settings\Crops\TreatmentTypesController::class;

Route::post('/{uuid?}', [$controller, 'create'])->whereUuid('uuid');
Route::get('/list', [$controller, 'listTreatmentTypes']);
Route::delete('/{uuid}', [$controller, 'delete'])->whereUuid('uuid');
