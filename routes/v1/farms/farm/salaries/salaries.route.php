<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\SalariesController::class;

Route::get('/list/{farm_uuid}', [$controller, 'index']);
Route::post('/', [$controller, 'store']);
