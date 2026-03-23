<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Users\UsersController::class;

Route::post('', [$controller, 'createFarmUser']);
Route::get('list', [$controller, 'listUsers']);
