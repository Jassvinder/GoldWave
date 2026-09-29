<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\RuleVersionService;
use App\Services\StoreActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * T-171 (28-09-2026, user decision — DOMAIN_LOGIC.md §16.2 T-171 note): a store Purchase/Repurchase (walk-in or
 * member, or a plan-jewellery delivery) gets its bill only when someone generates it — at any time after the sale.
 * Optional hallmarking (one HUID + charge per piece) is added before GST, so the sale's subtotal, GST and total are
 * updated once, here; income is unaffected (it is on the metal value). A bill is generated once; every later copy is a
 * duplicate with the same number.
 */
class GenerateStoreSaleBill
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly StoreActivityLogger $activityLog,
    ) {}

    /** @param list<array{huid: string, charge: float|int|string}> $hallmarks */
    public function __invoke(StoreSale $sale, User $operator, array $hallmarks = []): Invoice
    {
        $hallmarkTotal = RecordHallmarks::validate($hallmarks, (int) $sale->quantity);

        $invoice = DB::transaction(function () use ($sale, $hallmarks, $hallmarkTotal) {
            $locked = StoreSale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoice()->exists()) {
                throw ValidationException::withMessages(['bill' => 'This bill was already generated — open it to print a duplicate copy.']);
            }

            if ($hallmarkTotal > 0) {
                RecordHallmarks::store($hallmarks, ['store_sale_id' => $locked->id]);

                $subtotal = round((float) $locked->sale_amount + $hallmarkTotal, 2);
                $gstPercent = $locked->gst_percent !== null ? (float) $locked->gst_percent : (float) $this->rules->value('store_gst_percent', 0);
                $gstAmount = round($subtotal * $gstPercent / 100, 2);

                $locked->update([
                    'hallmark_charges' => round((float) $locked->hallmark_charges + $hallmarkTotal, 2),
                    'sale_amount' => $subtotal,
                    'gst_percent' => $gstPercent,
                    'gst_amount' => $gstAmount,
                    'total_invoice_amount' => round($subtotal + $gstAmount, 2),
                ]);
            }

            return Invoice::create([
                'store_sale_id' => $locked->id,
                'invoice_no' => 'INV-'.now()->format('Ymd').'-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT),
                'generated_at' => now(),
            ]);
        });

        $store = $sale->store()->firstOrFail();
        $this->activityLog->record($store, $operator, 'invoice_generated', $sale->member, $sale);

        return $invoice;
    }
}
