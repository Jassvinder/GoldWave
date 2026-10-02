import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer, Share2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate, formatRatePer10g } from '@/lib/utils';

type Invoice = {
    invoice_no: string;
    generated_at: string | null;
    /** T-171 — "original" right after the bill is generated; every later view is a duplicate copy. */
    copy: 'original' | 'duplicate';
    /** The store for a store bill, GoldWave itself for a company delivery (T-171). */
    seller: { name: string; location: string | null; contact: string | null };
    customer: { customer_id: string; name: string | null } | null;
    transaction_type:
        | 'new_sale'
        | 'purchase'
        | 'repurchase'
        | 'company_delivery';
    /** T-171 — one HUID and hallmark charge per hallmarked piece. */
    hallmarks: { piece_no: number; huid: string; charge: string }[];
    hallmark_charges: string | null;
    item_name: string;
    metal: string | null;
    item_weight: string | null;
    quantity: number;
    rate: string | null;
    /** T-169 — price breakdown; null on sales recorded before automatic pricing. */
    metal_value: string | null;
    making_charge_percent: string | null;
    making_charges: string | null;
    gst_percent: string | null;
    sale_amount: string;
    gst_amount: string;
    total_invoice_amount: string;
    payment_source: string | null;
    /** T-185c — a Repurchase on EMI handover: already paid through the EMIs, and due at the counter. */
    prepaid_amount?: string | null;
    amount_due?: string | null;
};

type Props = { invoice: Invoice; back_url: string };

const TRANSACTION_LABEL: Record<Invoice['transaction_type'], string> = {
    new_sale: 'Plan Jewellery Delivery',
    purchase: 'Purchase',
    repurchase: 'Repurchase',
    company_delivery: 'Plan Jewellery Delivery (Company)',
};

const PAYMENT_LABEL: Record<string, string> = {
    cash: 'Cash',
    other: 'Other',
    store_wallet: 'Store Wallet',
};

function customerLabel(invoice: Invoice): string {
    if (!invoice.customer) {
        return 'Walk-in customer';
    }

    return invoice.customer.name
        ? `${invoice.customer.name} (${invoice.customer.customer_id})`
        : invoice.customer.customer_id;
}

/** Plain-text summary for WhatsApp — the recipient is picked in WhatsApp itself. */
function whatsappText(invoice: Invoice): string {
    const weight = invoice.item_weight ? `, ${invoice.item_weight} g` : '';

    return [
        `Invoice ${invoice.invoice_no}${invoice.copy === 'duplicate' ? ' (duplicate copy)' : ''} — ${invoice.seller.name}`,
        `Date: ${formatDate(invoice.generated_at)}`,
        `Customer: ${customerLabel(invoice)}`,
        `Item: ${invoice.item_name}${invoice.metal ? ` (${invoice.metal}${weight})` : ''} × ${invoice.quantity}`,
        ...(invoice.hallmarks.length > 0
            ? [`HUID: ${invoice.hallmarks.map((h) => h.huid).join(', ')}`]
            : []),
        `Amount: ₹${invoice.sale_amount}`,
        `GST: ₹${invoice.gst_amount}`,
        `Total: ₹${invoice.total_invoice_amount}`,
        ...(invoice.prepaid_amount
            ? [
                  `Paid through Repurchase EMIs: ₹${invoice.prepaid_amount}`,
                  `Paid at delivery: ₹${invoice.amount_due}`,
              ]
            : []),
        'Thank you for shopping with GoldWave.',
    ].join('\n');
}

/**
 * T-160 (28-09-2026) — DOMAIN_LOGIC.md §16.2: a confirmed store sale's
 * invoice, printable (the browser's Print also offers Save as PDF) and
 * shareable via WhatsApp. Rendered without portal chrome (see app.tsx).
 */
export default function InvoiceShow({ invoice, back_url }: Props) {
    const shareUrl = `https://wa.me/?text=${encodeURIComponent(whatsappText(invoice))}`;

    return (
        <>
            <Head title={`Invoice ${invoice.invoice_no}`} />

            <div className="bg-muted/40 min-h-screen px-4 py-6 print:bg-white print:p-0">
                <div className="mx-auto flex max-w-2xl flex-col gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-2 print:hidden">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={back_url}>
                                <ArrowLeft />
                                Back
                            </Link>
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer />
                                Print
                            </Button>
                            <Button asChild>
                                <a
                                    href={shareUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Share2 />
                                    Share via WhatsApp
                                </a>
                            </Button>
                        </div>
                    </div>

                    <div className="bg-background rounded-xl border p-6 shadow-sm sm:p-8 print:rounded-none print:border-0 print:shadow-none">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                            <div className="flex items-center gap-3">
                                <img
                                    src="/Images/Logo.webp"
                                    alt="GoldWave"
                                    className="size-12 rounded-full object-cover"
                                />
                                <div>
                                    <div className="text-lg font-semibold">
                                        {invoice.seller.name}
                                    </div>
                                    {invoice.seller.location && (
                                        <div className="text-muted-foreground text-sm">
                                            {invoice.seller.location}
                                        </div>
                                    )}
                                    {invoice.seller.contact && (
                                        <div className="text-muted-foreground text-sm">
                                            {invoice.seller.contact}
                                        </div>
                                    )}
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-xl font-semibold tracking-wide">
                                    INVOICE
                                </div>
                                <div
                                    className={
                                        invoice.copy === 'duplicate'
                                            ? 'text-destructive text-xs font-semibold tracking-wide'
                                            : 'text-muted-foreground text-xs font-semibold tracking-wide'
                                    }
                                >
                                    {invoice.copy === 'duplicate'
                                        ? 'DUPLICATE COPY'
                                        : 'ORIGINAL'}
                                </div>
                                <div className="text-sm">
                                    {invoice.invoice_no}
                                </div>
                                <div className="text-muted-foreground text-sm">
                                    {formatDate(invoice.generated_at)}
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 py-5 text-sm sm:grid-cols-2">
                            <div>
                                <div className="text-muted-foreground text-xs uppercase">
                                    Billed to
                                </div>
                                <div className="font-medium">
                                    {customerLabel(invoice)}
                                </div>
                            </div>
                            <div className="sm:text-right">
                                <div className="text-muted-foreground text-xs uppercase">
                                    Transaction
                                </div>
                                <div className="font-medium">
                                    {
                                        TRANSACTION_LABEL[
                                            invoice.transaction_type
                                        ]
                                    }
                                    {invoice.payment_source && (
                                        <>
                                            {' '}
                                            · Paid by{' '}
                                            {PAYMENT_LABEL[
                                                invoice.payment_source
                                            ] ?? invoice.payment_source}
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-y text-left text-xs uppercase">
                                        <th className="py-2 pr-3 font-medium">
                                            Item
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Weight
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Qty
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Rate
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Amount
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="border-b">
                                        <td className="py-3 pr-3">
                                            <div className="font-medium">
                                                {invoice.item_name}
                                            </div>
                                            {invoice.metal && (
                                                <div className="text-muted-foreground text-xs capitalize">
                                                    {invoice.metal}
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-3 pr-3">
                                            {invoice.item_weight
                                                ? `${invoice.item_weight} g`
                                                : '—'}
                                        </td>
                                        <td className="py-3 pr-3">
                                            {invoice.quantity}
                                        </td>
                                        <td className="py-3 pr-3">
                                            {invoice.rate
                                                ? formatRatePer10g(invoice.rate)
                                                : '—'}
                                        </td>
                                        <td className="py-3 text-right">
                                            ₹
                                            {invoice.metal_value ??
                                                invoice.sale_amount}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {invoice.hallmarks.length > 0 && (
                            <div className="pt-4 text-sm">
                                <div className="text-muted-foreground mb-1 text-xs uppercase">
                                    Hallmark (HUID)
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    {invoice.hallmarks.map((piece) => (
                                        <div
                                            key={piece.piece_no}
                                            className="flex justify-between gap-4"
                                        >
                                            <span>
                                                Piece {piece.piece_no} · HUID{' '}
                                                <span className="font-mono">
                                                    {piece.huid}
                                                </span>
                                            </span>
                                            <span>₹{piece.charge}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="ml-auto flex w-full max-w-xs flex-col gap-1 pt-4 text-sm">
                            {invoice.making_charges !== null && (
                                <>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Metal value
                                        </span>
                                        <span>₹{invoice.metal_value}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Making charges (
                                            {invoice.making_charge_percent}%)
                                        </span>
                                        <span>₹{invoice.making_charges}</span>
                                    </div>
                                </>
                            )}
                            {invoice.hallmark_charges !== null && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Hallmark charges
                                    </span>
                                    <span>₹{invoice.hallmark_charges}</span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    {invoice.making_charges !== null
                                        ? 'Subtotal'
                                        : 'Amount'}
                                </span>
                                <span>₹{invoice.sale_amount}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    GST
                                    {invoice.gst_percent !== null
                                        ? ` (${invoice.gst_percent}%)`
                                        : ''}
                                </span>
                                <span>₹{invoice.gst_amount}</span>
                            </div>
                            <div className="mt-1 flex justify-between border-t pt-2 text-base font-semibold">
                                <span>Total</span>
                                <span>₹{invoice.total_invoice_amount}</span>
                            </div>
                            {invoice.prepaid_amount && (
                                <>
                                    <div className="flex justify-between">
                                        <span>
                                            Less: paid through Repurchase EMIs
                                        </span>
                                        <span>− ₹{invoice.prepaid_amount}</span>
                                    </div>
                                    <div className="flex justify-between font-semibold">
                                        <span>Paid at delivery</span>
                                        <span>₹{invoice.amount_due}</span>
                                    </div>
                                </>
                            )}
                        </div>

                        <p className="text-muted-foreground mt-8 text-center text-xs">
                            Thank you for shopping with GoldWave. This is a
                            computer-generated invoice.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
