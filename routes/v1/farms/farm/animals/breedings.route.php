<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Animals\BreedingsController::class;
Route::get("calendar", [$controller,"calendar"]);
Route::get("inbreeding-check", [$controller,"inbreedingCheck"]);
Route::get("list/{uuid}", [$controller,"listBreedings"]);
Route::post("/", [$controller,"store"]);
Route::post("{uuid}/birth", [$controller,"registerBirth"]);
Route::put("{uuid}", [$controller,"update"]);
