<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §12.2(b) — T-153. A single company-level wallet,
     * credited when a Member/Store funds an Assisted Registration from
     * their own wallet balance — genuinely new money reaching the company,
     * unlike T-151's Store-Wallet-Funded Cash Collection (which settles a
     * store's already-paid advance, so it never touches this wallet). A
     * true singleton — `CompanyWalletService::wallet()` uses
     * `firstOrCreate([], ['balance' => 0])` rather than a seeder, mirroring
     * how every other wallet-balance table in this project is the cached
     * read-performance figure, with `company_wallet_ledger_entries` as the
     * audit source of truth (DOMAIN_LOGIC.md §12).
     */
    public function up(): void
    {
        Schema::create('company_wallets', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('company_wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_wallet_id')->constrained('company_wallets');
            $table->enum('entry_type', ['credit', 'debit']);
            $table->string('category');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_wallet_id', 'category']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_wallet_ledger_entries');
        Schema::dropIfExists('company_wallets');
    }
};
