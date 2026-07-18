<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Bees\ApiariesController;

$controller = ApiariesController::class;

Route::get('/{uuid}/profile', [$controller, 'getProfile'])->whereUuid('uuid');
Route::post('/{uuid}/profile', [$controller, 'upsertProfile'])->whereUuid('uuid');
