<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Users\PersonnelsController::class;
Route::post('/',[$controller,'storePersonnel']);
Route::get('/list', [$controller, 'listPersonnels']);
