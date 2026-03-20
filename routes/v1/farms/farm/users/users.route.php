<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Users\UsersController::class;

Route::get('list', [$controller, 'listUsers']);
