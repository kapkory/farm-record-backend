<?php
$controller = \App\Http\Controllers\Api\v1\Settings\System\LedgerAccountsController::class;
Route::post('/',[$controller,'storeLedgerAccount']);
Route::get('/list',[$controller,'listLedgerAccounts']);
Route::delete('/delete/{ledgeraccount}',[$controller,'destroyTransactionCategory']);
