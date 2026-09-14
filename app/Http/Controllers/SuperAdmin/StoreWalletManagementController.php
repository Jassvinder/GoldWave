<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Store\RecordStoreWalletTopup;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RecordStoreWalletTopupRequest;
use App\Models\Store;
use App\Models\StoreWalletLedgerEntry;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S10 — view/credit Store Wallets, top-ups, advance balance, ledger history. */
class StoreWalletManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $stores = Store::with('wallet')
            ->orderBy('name')
            ->get()
            ->map(function (Store $store): array {
                $balance = $store->wallet !== null ? $store->wallet->balance : 0;

                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'advance_amount' => $store->advance_amount,
                    'wallet_balance' => (string) $balance,
                    'wallet_status' => $store->wallet?->status,
                ];
            });

        return Inertia::render('super-admin/store-wallet-management', [
            'stores' => $stores,
        ]);
    }

    public function show(Request $request, Store $store): Response
    {
        $store->load('wallet');
        $walletBalance = $store->wallet !== null ? $store->wallet->balance : 0;

        $entries = $store->wallet !== null
            ? StoreWalletLedgerEntry::where('store_wallet_id', $store->wallet->id)
                ->with('operator')
                ->orderByDesc('id')
                ->get()
                ->map(fn (StoreWalletLedgerEntry $entry): array => [
                    'type' => $entry->type,
                    'amount' => $entry->amount,
                    'reference' => $entry->reference,
                    'description' => $entry->description,
                    'operator_name' => $entry->operator?->name,
                    'occurred_at' => Dates::date($entry->occurred_at),
                ])
            : collect();

        return Inertia::render('super-admin/store-wallet-detail', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'advance_amount' => $store->advance_amount,
                'wallet_balance' => (string) $walletBalance,
            ],
            'entries' => $entries,
        ]);
    }

    public function topup(RecordStoreWalletTopupRequest $request, Store $store, RecordStoreWalletTopup $action): RedirectResponse
    {
        $wallet = $store->wallet()->firstOrFail();

        $action($wallet, (float) $request->input('amount'), $request->user(), $request->string('description')->toString() ?: null);

        return redirect()->route('super-admin.store-wallets.show', $store)->with('status', 'Store Wallet topped up.');
    }
}
