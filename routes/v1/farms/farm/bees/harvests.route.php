<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Bees\HarvestsController;

$controller = HarvestsController::class;

Route::post('/', [$controller, 'store']);
Route::get('/list', [$controller, 'list']);
