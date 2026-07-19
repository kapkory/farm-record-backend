<?php

use App\Http\Controllers\Api\v1\Farms\Farm\ProductionsController;

$controller = ProductionsController::class;
Route::post('/store', [$controller, 'store']);
Route::get('unlinked', [$controller, 'listUnlinked']);
Route::get('summary', [$controller, 'productionSummary']);
Route::get('list/{plantingUuid}', [$controller, 'listHarvests']);
