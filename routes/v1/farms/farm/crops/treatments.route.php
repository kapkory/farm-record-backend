<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Crops\TreatmentsController;

$controller = TreatmentsController::class;
Route::get('/list/{plantingUuid}', [$controller, 'listPlantingTreatments']);
Route::post('/', [$controller, 'storeTreatment']);
