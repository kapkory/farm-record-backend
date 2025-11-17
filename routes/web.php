<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    dd(\Illuminate\Support\Facades\Hash::make('leviskapkory@gmail.com'));
    return ['Laravel' => app()->version()];
});

require __DIR__.'/auth.php';
