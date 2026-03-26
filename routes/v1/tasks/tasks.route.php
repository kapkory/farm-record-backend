<?php

use App\Http\Controllers\Api\v1\Tasks\TasksController;

$controller = TasksController::class;

Route::get('/list/{taskable_uuid?}',     [$controller, 'listTasks']);
Route::post('/',                 [$controller, 'storeTask']);
Route::get('/{uuid}',            [$controller, 'showTask'])->whereUuid('uuid');
Route::put('/{uuid}',            [$controller, 'updateTask'])->whereUuid('uuid');
Route::patch('/{uuid}/status',   [$controller, 'updateStatus'])->whereUuid('uuid');
Route::delete('/{uuid}',         [$controller, 'deleteTask'])->whereUuid('uuid');

