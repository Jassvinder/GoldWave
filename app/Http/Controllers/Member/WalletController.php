<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\WalletLedgerEntry;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M14 — balance + full transaction ledger (DOMAIN_LOGIC.md §12). */
class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $entries = $member->walletLedgerEntries()
            ->orderByDesc('id')
            ->get()
            ->map($this->mapEntry(...));

        return Inertia::render('member/wallet', [
            'wallet_balance' => (string) $member->wallet_balance,
            'wallet_hold_amount' => (string) $member->wallet_hold_amount,
            'entries' => $entries,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapEntry(WalletLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'category' => $entry->category,
            'amount' => $entry->amount,
            'status' => $entry->status,
            'description' => $entry->description,
            'processed_at' => Dates::date($entry->processed_at),
            'created_at' => Dates::date($entry->created_at),
        ];
    }
}
