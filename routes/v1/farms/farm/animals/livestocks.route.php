<?php

$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Animals\LivestocksController::class;

// GET /api/v1/farms/farm/animals/livestocks/list/{farm_uuid?}
// Query params: tracking_type, animal_type_id, status
Route::get('/list/{farm_uuid?}', [$controller, 'index']);

// GET /api/v1/farms/farm/animals/livestocks/{animal_uuid}
Route::get('/{animal_uuid}', [$controller, 'show']);

