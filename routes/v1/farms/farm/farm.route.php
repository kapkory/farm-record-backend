<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\FarmController::class;
Route::get('/{farm_uuid}', [$controller, 'show']);
