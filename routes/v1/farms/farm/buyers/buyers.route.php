<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Buyers\BuyersController;

$controller = BuyersController::class;

Route::get('/list', [$controller, 'listBuyers']);
Route::post('/', [$controller, 'storeBuyer']);
