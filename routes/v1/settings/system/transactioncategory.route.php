<?php
$controller = \App\Http\Controllers\Api\v1\Settings\System\TransactionsCategoryController::class;
Route::post('/',[$controller,'storeTransactionCategory']);
Route::get('/list',[$controller,'listTransactionCategories']);
Route::delete('/delete/{transactioncategory}',[$controller,'destroyTransactionCategory']);
