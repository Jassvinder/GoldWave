<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreProfitDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md A06 — assigned-store sales, inventory, profit, and
 * distribution reports. Same "synchronous CSV of my own scope" shape as the
 * Member Portal's M17 (T-015) — a store's own data is never large enough to
 * need PERFORMANCE_GUIDE.md's queued-export treatment; the full cross-role
 * report catalog is T-018's job.
 */
class StoreReportsController extends Controller
{
    private const REPORTS = [
        'sales' => 'Store Sales',
        'inventory' => 'Inventory',
        'distributions' => 'Store Profit Distributions',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('admin/reports', [
            'reports' => self::REPORTS,
        ]);
    }

    public function download(Request $request, string $report): HttpResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        abort_unless(array_key_exists($report, self::REPORTS), 404);

        [$header, $rows] = match ($report) {
            'sales' => [
                ['Type', 'Customer ID', 'Item', 'Amount', 'Status', 'Date'],
                $store->sales()->with('member')->orderByDesc('id')->get()
                    ->map(fn ($s) => [$s->transaction_type, $s->member?->customer_id, $s->item_name, $s->total_invoice_amount, $s->status, $s->created_at?->toDateString()]),
            ],
            'inventory' => [
                ['Item', 'Metal', 'Weight', 'Quantity', 'Price'],
                $store->inventoryItems()->orderBy('item_name')->get()
                    ->map(fn ($i) => [$i->item_name, $i->metal, $i->weight, $i->quantity, $i->price]),
            ],
            'distributions' => [
                ['Beneficiary Type', 'Customer ID', 'Rate %', 'Amount', 'Store Sale ID'],
                StoreProfitDistribution::whereHas('storeSale', fn ($q) => $q->where('store_id', $store->id))
                    ->with('beneficiary')->orderByDesc('id')->get()
                    ->map(fn ($d) => [$d->beneficiary_type, $d->beneficiary?->customer_id, $d->rate_percent, $d->amount, $d->store_sale_id]),
            ],
        };

        $csv = implode(',', $header)."\n".$rows->map(fn ($row) => implode(',', $row))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"store-{$report}.csv\"",
        ]);
    }
}
