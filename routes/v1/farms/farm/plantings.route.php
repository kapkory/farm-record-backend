<?php
$controller =\App\Http\Controllers\Api\v1\Farms\Farm\PlantingsController::class;
Route::get('/',[$controller,'index']);
Route::post('/',[$controller,'storePlanting']);
Route::get('/list',[$controller,'listPlantings']);
Route::delete('/delete/{planting}',[$controller,'destroyPlanting']);
