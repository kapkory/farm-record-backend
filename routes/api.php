<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Include auth routes (login, register, etc.)
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
   return $request->user()->only(['uuid', 'name', 'email']);
});
