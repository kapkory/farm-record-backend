<?php

use App\Http\Controllers\Api\v1\Settings\Animals\TreatmentPlanController;

$controller = TreatmentPlanController::class;

Route::post('/', [$controller, 'storeTreatmentPlan']);
Route::get('/list', [$controller, 'listTreatmentPlans']);
Route::get('/{uuid}', [$controller, 'showTreatmentPlan'])->whereUuid('uuid');
Route::put('/{uuid}', [$controller, 'updateTreatmentPlan'])->whereUuid('uuid');
Route::delete('/{uuid}', [$controller, 'destroyTreatmentPlan'])->whereUuid('uuid');
