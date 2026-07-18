<?php

use App\Http\Controllers\Api\v1\Farms\Farm\Bees\ReportsController;

$controller = ReportsController::class;

Route::get('/production', [$controller, 'production']);
