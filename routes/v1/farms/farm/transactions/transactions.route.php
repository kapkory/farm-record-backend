<?php
$controller = \App\Http\Controllers\Api\v1\Farms\Farm\TransactionsController::class;

// Money: owner/manager only. Staff logins are blocked server-side, not just
// hidden in the UI.
Route::middleware('finances')->group(function () use ($controller) {
    Route::post('/', [$controller, 'storeTransaction']);
    Route::get('/list/{transactionable_type?}/{transactionable_id}', [$controller, 'listTransactions']);
});
