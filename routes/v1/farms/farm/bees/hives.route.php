<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Bees\HivesController;

$controller = HivesController::class;

Route::post('/', [$controller, 'store']);
Route::post('/bulk', [$controller, 'storeBulk']);
Route::get('/list', [$controller, 'list']);
Route::get('/{uuid}', [$controller, 'show'])->whereUuid('uuid');
Route::put('/{uuid}', [$controller, 'update'])->whereUuid('uuid');
Route::delete('/{uuid}', [$controller, 'destroy'])->whereUuid('uuid');
