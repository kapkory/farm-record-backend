<?php
$controller = \App\Http\Controllers\Api\v1\Settings\Crops\VarietiesController::class;
Route::post('/',[$controller,'create']);
Route::get('/list',[$controller,'listVarieties']);
Route::delete('/{uuid}', [$controller, 'delete'])->whereUuid('uuid');
