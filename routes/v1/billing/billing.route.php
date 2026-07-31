<?php

use App\Http\Controllers\Api\v1\Billing\SubscriptionController;

$controller = SubscriptionController::class;

Route::get('/plans', [$controller, 'plans']);
Route::get('/subscription', [$controller, 'show']);
Route::post('/subscribe', [$controller, 'subscribe']);
