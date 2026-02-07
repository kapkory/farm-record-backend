<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Include auth routes (login, register, etc.)
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
   return $request->user()->only(['uuid', 'name', 'email']);
});

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('farms', \App\Http\Controllers\Api\v1\Farms\FarmsController::class);
});


Route::group(['middleware' => ['auth:sanctum',]], function () {
    $agent = new \Jenssegers\Agent\Agent();

    $routes_path = base_path('routes');

    $route_files = File::allFiles(base_path('routes'));
    $windowsVersion = false;
    if ($agent->isWindows()) {
        if (str_contains($routes_path, '\\'))
            $windowsVersion = true;
    }

    foreach ($route_files as $file) {
        $path = $file->getPath();

        if ($path != $routes_path) {
            $file_name = $file->getFileName();
            $prefix = str_replace($file_name, '', $path);
            $prefix = str_replace($routes_path, '', $prefix);
            $file_path = $file->getPathName();
            $this->route_path = $file_path;
            $arr = explode('/', $prefix);
            // if windows if less than version 10
            $agent->is('Windows') && $windowsVersion ? $arr = explode('\\', $prefix) : $arr = explode('/', $prefix);
            // $arr = explode('/', $prefix);
            $len = count($arr);
            $main_file = $arr[$len - 1];
            $arr = array_map('ucwords', $arr);
            $arr = array_filter($arr);
            $ext_route = str_replace('index.route.php', '', $file_name);
            $ext_route = str_replace($main_file, '', $ext_route);
            $ext_route = str_replace('.route.php', '', $ext_route);
            $ext_route = str_replace('web', '', $ext_route);

            // if windows is older than version 10
            if ($agent->is('Windows') && $windowsVersion)
                $prefix = str_replace('\\', '/', strtolower($prefix . '/' . $ext_route));
            else
                $prefix = strtolower($prefix . '/' . $ext_route);

            $implode = ($agent->is('Windows') && $windowsVersion) ? implode('/', $arr) : implode('\\', $arr);
            // $implode = implode('\\', $arr);

//            $implode = implode('\\', $arr) ;
            Route::group(['namespace' => $implode, 'prefix' => $prefix], function () {
                require $this->route_path;
            });
        }
    }
});
