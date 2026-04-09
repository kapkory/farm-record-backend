<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\TransactionsController::class;
Route::post('/',[$controller,'storeTransaction']);
Route::get('/list/{transactionable_type?}/{transactionable_id}',[$controller,'listTransactions']);
