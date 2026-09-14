<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreSale;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md A05 — transaction details, invoice/reference, member,
 * item/weight/rate/amount, Store Wallet deduction, status, operational
 * history. The "operational history" half is this store's own
 * `store_activity_logs` slice (DOMAIN_LOGIC.md §16.3) — full company-wide
 * audit-log visibility stays Super Admin-only (§17.2), but an Admin/Store
 * Owner can see their own store's activity.
 */
class StoreTransactionsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $sales = StoreSale::where('store_id', $store->id)
            ->with(['member', 'invoice', 'storeWalletDeduction'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (StoreSale $sale): array => [
                'id' => $sale->id,
                'transaction_type' => $sale->transaction_type,
                'customer_id' => $sale->member?->customer_id,
                'item_name' => $sale->item_name,
                'item_weight' => $sale->item_weight,
                'rate' => $sale->rate,
                'total_invoice_amount' => $sale->total_invoice_amount,
                'payment_source' => $sale->payment_source,
                'store_wallet_deduction_reference' => $sale->storeWalletDeduction?->reference,
                'status' => $sale->status,
                'distribution_status' => $sale->distribution_status,
                'invoice_no' => $sale->invoice?->invoice_no,
                'created_at' => $sale->created_at?->toDateString(),
            ]);

        $activity = StoreActivityLog::where('store_id', $store->id)
            ->with(['operator', 'affectedMember'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (StoreActivityLog $log): array => [
                'action_type' => $log->action_type,
                'operator_name' => $log->operator?->name,
                'affected_customer_id' => $log->affectedMember?->customer_id,
                'occurred_at' => Dates::date($log->occurred_at),
            ]);

        return Inertia::render('admin/transactions', [
            'sales' => $sales,
            'activity' => $activity,
        ]);
    }
}
