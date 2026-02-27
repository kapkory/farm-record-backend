<?php

$controller = \App\Http\Controllers\Api\v1\Farms\FieldsController::class;

Route::post('/{uuid?}', [$controller, 'create'])->whereUuid('uuid');
Route::get('/list/{uuid?}', [$controller, 'listFields']);
Route::get('/{uuid}', [$controller, 'show'])->whereUuid('uuid');
Route::delete('/{uuid}', [$controller, 'delete'])->whereUuid('uuid');
Route::patch('/{uuid}/toggle-status', [$controller, 'toggleStatus'])->whereUuid('uuid');
