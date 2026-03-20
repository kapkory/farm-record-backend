<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Reports\ReportsController;

$controller = ReportsController::class;

Route::get('/profit-and-loss/plantings', [$controller, 'profitAndLossByPlantings']);
