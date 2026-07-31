<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Sales\SalesController;

$controller = SalesController::class;

Route::get('/list', [$controller, 'listSales']);
Route::get('/summary', [$controller, 'salesSummary']);
Route::get('/income/{sellable_type}/{sellable_uuid}', [$controller, 'sellableIncome'])->whereUuid('sellable_uuid');
Route::post('/', [$controller, 'storeSale']);
Route::get('/{uuid}', [$controller, 'showSale'])->whereUuid('uuid');
Route::post('/{uuid}/payments', [$controller, 'storePayment'])->whereUuid('uuid');
Route::post('/{uuid}/void', [$controller, 'voidSale'])->whereUuid('uuid');
