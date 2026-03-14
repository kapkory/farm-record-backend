<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\PlantingController::class;
Route::get('/{planting_uuid}', [$controller, 'show'])->whereUuid('planting_uuid');
