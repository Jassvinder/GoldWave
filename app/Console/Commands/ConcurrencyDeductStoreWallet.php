<?php

namespace App\Console\Commands;

use App\Models\StoreWallet;
use App\Models\User;
use App\Services\StoreWalletService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * T-020 (DOMAIN_LOGIC.md §21) — a single-purpose command launched as a real,
 * separate OS process by tests/Concurrency/StoreConcurrencyTest.php to
 * produce a genuine race against StoreWalletService::deduct()'s
 * lockForUpdate() critical section. Never registered as user-facing
 * functionality — exists purely as a concurrency-test worker.
 */
class ConcurrencyDeductStoreWallet extends Command
{
    protected $signature = 'concurrency:deduct-store-wallet {walletId} {amount} {operatorId}';

    protected $description = 'Test worker: attempt one Store Wallet deduction, for real-process concurrency testing.';

    public function handle(StoreWalletService $service): int
    {
        $wallet = StoreWallet::findOrFail((int) $this->argument('walletId'));
        $operator = User::findOrFail((int) $this->argument('operatorId'));

        try {
            $service->deduct($wallet, (float) $this->argument('amount'), $operator, 'Concurrency test deduction');
            $this->line('SUCCESS');

            return self::SUCCESS;
        } catch (ValidationException) {
            $this->line('BLOCKED');

            return self::FAILURE;
        }
    }
}
