<?php
$controller = \App\Http\Controllers\Api\v1\User\UserController::class;

Route::put('password', [$controller, 'updatePassword']);
