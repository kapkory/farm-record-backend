<?php
namespace App\Contracts;

use App\DTOs\LedgerTransactionDTO;
use App\Models\Core\LedgerTransaction;

interface LedgerTransactionHandlerInterface{
    public function handle(LedgerTransactionDTO $ledgerTransactionDTO) : LedgerTransaction;

}
