<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\Animals\BreedingsController::class;
Route::get("list/{uuid}", [$controller,"listBreedings"]);
Route::post("/", [$controller,"store"]);
Route::put("{uuid}", [$controller,"update"]);
