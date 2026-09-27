<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\WalletLedgerEntry;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M14 — balance + transaction ledger, paginated with search and column sorting (T-126, DOMAIN_LOGIC.md §12). */
class WalletController extends Controller
{
    private const PER_PAGE = 15;

    /** Sortable column key → SQL expression. Amount sorts by its signed value (debits negative) to match what the table shows. */
    private const SORT_EXPRESSIONS = [
        'category' => 'category',
        'description' => 'description',
        'processed_at' => 'COALESCE(processed_at, created_at)',
        'amount' => "CASE WHEN entry_type = 'debit' THEN -amount ELSE amount END",
        'status' => 'status',
    ];

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $query = $member->walletLedgerEntries();

        $search = trim($request->string('search')->toString());

        if ($search !== '') {
            $term = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], $search)).'%';
            // Categories are stored snake_case but displayed as words, so "level income" must match `level_income`.
            $spacedTerm = '%'.mb_strtolower(str_replace(['%', '_', ' '], ['\%', '\_', '\_'], $search)).'%';

            $query->where(function ($q) use ($term, $spacedTerm): void {
                $q->whereRaw("LOWER(category) LIKE ? ESCAPE '\\'", [$spacedTerm])
                    ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ? ESCAPE '\\'", [$term])
                    ->orWhereRaw("LOWER(status) LIKE ? ESCAPE '\\'", [$term]);
            });
        }

        $sortKey = $request->string('sort')->toString();

        if (isset(self::SORT_EXPRESSIONS[$sortKey])) {
            $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
            $query->orderByRaw(self::SORT_EXPRESSIONS[$sortKey]." {$direction}")->orderByDesc('id');
        } else {
            $sortKey = null;
            $direction = 'desc';
            $query->orderByDesc('id');
        }

        $entries = $query->paginate(self::PER_PAGE)->withQueryString();

        $entries->through(fn (WalletLedgerEntry $entry): array => $this->mapEntry($entry));

        return Inertia::render('member/wallet', [
            'wallet_balance' => (string) $member->wallet_balance,
            'wallet_hold_amount' => (string) $member->wallet_hold_amount,
            'entries' => $entries,
            'filters' => ['search' => $search],
            'sort' => $sortKey !== null ? ['key' => $sortKey, 'direction' => $direction] : null,
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
