<?php
$controller = \App\Http\Controllers\Api\v1\Settings\Crops\ScheduleController::class;

Route::post('/', [$controller, 'storeSchedule']);
Route::put('/{uuid}', [$controller, 'updateSchedule']);
Route::get('/list', [$controller, 'listSchedules']);
Route::get('/{uuid}', [$controller, 'showSchedule']);
Route::delete('/{uuid}', [$controller, 'destroySchedule']);
