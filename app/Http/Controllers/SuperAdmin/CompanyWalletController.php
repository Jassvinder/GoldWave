<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CompanyWalletLedgerEntry;
use App\Services\CompanyWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §12.2(b) — T-153. Super Admin's simple balance + ledger
 * view of the Company Wallet, mirroring Store Wallet Management's shape
 * (S10) at a smaller scale — nothing spends out of it yet (§0's "no
 * refunds, ever" and this project's confirmed scope so far never needs a
 * debit from this wallet).
 */
class CompanyWalletController extends Controller
{
    public function index(CompanyWalletService $companyWallet): Response
    {
        $wallet = $companyWallet->wallet();

        $entries = CompanyWalletLedgerEntry::where('company_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (CompanyWalletLedgerEntry $entry): array => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'category' => $entry->category,
                'amount' => $entry->amount,
                'description' => $entry->description,
                'occurred_at' => $entry->occurred_at,
            ]);

        return Inertia::render('super-admin/company-wallet', [
            'balance' => $wallet->balance,
            'entries' => $entries,
        ]);
    }

    /** T-163 (28-09-2026) — Super Admin adds money to the Company Wallet by hand, with an optional note. */
    public function topUp(Request $request, CompanyWalletService $companyWallet): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $companyWallet->topUp((float) $data['amount'], $request->user(), $data['description'] ?? null);

        return redirect()->route('super-admin.company-wallet.index')
            ->with('status', 'Company Wallet topped up by ₹'.number_format((float) $data['amount'], 2).'.');
    }
}
