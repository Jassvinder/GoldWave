<?php

namespace App\Services;

use App\Models\CompanyDelivery;
use App\Models\HallmarkEntry;
use App\Models\Invoice;
use App\Models\StoreSale;
use App\Support\Dates;

/**
 * T-160/T-171 — the one shape the printable bill page (`invoices/show`) renders, whether the bill is a store sale's
 * or a company delivery's, and whichever portal opens it (Store, Super Admin). `copy` is `original` only right after
 * the bill was generated; every later view is a `duplicate` with the same number (DOMAIN_LOGIC.md §16.2 T-171 note).
 */
class InvoicePresenter
{
    /** @return array<string, mixed> */
    public static function forStoreSale(StoreSale $sale, bool $original): array
    {
        $sale->loadMissing(['invoice', 'member.user', 'store', 'hallmarks']);
        /** @var Invoice $invoice */
        $invoice = $sale->invoice;

        return self::shape($invoice, $original, [
            'seller' => [
                'name' => $sale->store->name ?? 'GoldWave Store',
                'location' => $sale->store?->location,
                'contact' => $sale->store?->contact,
            ],
            'customer' => $sale->member ? [
                'customer_id' => $sale->member->customer_id,
                'name' => $sale->member->user?->name,
            ] : null,
            'transaction_type' => $sale->transaction_type,
            'item_name' => $sale->item_name,
            'metal' => $sale->metal,
            'item_weight' => $sale->item_weight,
            'quantity' => $sale->quantity,
            'rate' => $sale->rate,
            'metal_value' => $sale->metal_value,
            'making_charge_percent' => $sale->making_charge_percent,
            'making_charges' => $sale->making_charges,
            'hallmark_charges' => $sale->hallmark_charges !== null && (float) $sale->hallmark_charges > 0 ? $sale->hallmark_charges : null,
            'hallmarks' => $sale->hallmarks->map(self::hallmark(...))->all(),
            'gst_percent' => $sale->gst_percent,
            'sale_amount' => $sale->sale_amount,
            'gst_amount' => $sale->gst_amount,
            'total_invoice_amount' => $sale->total_invoice_amount,
            'payment_source' => $sale->payment_source,
            // T-185c — a Repurchase on EMI handover: what the EMIs already paid, and what is due at the counter.
            'prepaid_amount' => $sale->prepaid_amount,
            'amount_due' => $sale->prepaid_amount !== null ? number_format((float) $sale->total_invoice_amount - (float) $sale->prepaid_amount, 2, '.', '') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public static function forCompanyDelivery(CompanyDelivery $delivery, bool $original): array
    {
        $delivery->loadMissing(['invoice', 'member.user', 'hallmarks']);
        /** @var Invoice $invoice */
        $invoice = $delivery->invoice;

        return self::shape($invoice, $original, [
            'seller' => ['name' => config('app.name', 'GoldWave'), 'location' => null, 'contact' => null],
            'customer' => [
                'customer_id' => $delivery->member->customer_id,
                'name' => $delivery->member->user?->name,
            ],
            'transaction_type' => 'company_delivery',
            'item_name' => $delivery->item_name,
            'metal' => $delivery->metal,
            'item_weight' => $delivery->item_weight,
            'quantity' => $delivery->quantity,
            'rate' => $delivery->rate,
            'metal_value' => $delivery->metal_value,
            'making_charge_percent' => $delivery->making_charge_percent,
            'making_charges' => $delivery->making_charges,
            'hallmark_charges' => (float) $delivery->hallmark_charges > 0 ? $delivery->hallmark_charges : null,
            'hallmarks' => $delivery->hallmarks->map(self::hallmark(...))->all(),
            'gst_percent' => $delivery->gst_percent,
            'sale_amount' => $delivery->sale_amount,
            'gst_amount' => $delivery->gst_amount,
            'total_invoice_amount' => $delivery->total_invoice_amount,
            'payment_source' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return array<string, mixed>
     */
    private static function shape(Invoice $invoice, bool $original, array $lines): array
    {
        return [
            'invoice_no' => $invoice->invoice_no,
            'generated_at' => Dates::date($invoice->generated_at),
            'copy' => $original ? 'original' : 'duplicate',
        ] + $lines;
    }

    /** @return array{piece_no: int, huid: string, charge: string} */
    private static function hallmark(HallmarkEntry $entry): array
    {
        return ['piece_no' => (int) $entry->piece_no, 'huid' => (string) $entry->huid, 'charge' => (string) $entry->charge];
    }
}
