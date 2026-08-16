<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Reports\ReportsController;

$controller = ReportsController::class;

// Profit and loss is a money view — owner/manager only.
Route::middleware('finances')->group(function () use ($controller) {
    Route::get('/profit-and-loss/plantings', [$controller, 'profitAndLossByPlantings']);
    Route::get('/profit-and-loss', [$controller, 'profitAndLoss']);
    Route::get('/balance-sheet', [$controller, 'balanceSheet']);
    Route::get('/cash-flow', [$controller, 'cashFlow']);
});
