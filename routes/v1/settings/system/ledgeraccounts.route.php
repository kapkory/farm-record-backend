<?php
$controller = \App\Http\Controllers\Api\v1\Settings\System\LedgerAccountsController::class;
// The chart of accounts is finance configuration — owner/manager only.
Route::middleware('finances')->group(function () use ($controller) {
    Route::post('/', [$controller, 'storeLedgerAccount']);
    Route::get('/list', [$controller, 'listLedgerAccounts']);
    Route::delete('/delete/{ledgeraccount}', [$controller, 'destroyTransactionCategory']);
});
