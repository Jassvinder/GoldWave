<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Billing\RecordCompanyDelivery;
use App\Actions\Store\PriceStoreSale;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Controller;
use App\Models\CompanyDelivery;
use App\Models\Member;
use App\Models\StoreSale;
use App\Services\InvoicePresenter;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-171 (28-09-2026, user decisions — DOMAIN_LOGIC.md §16.2 T-171 note): the company's own plan-jewellery delivery
 * (Super Admin, direct entry, no stock, no income, billed at once) and Super Admin's view of every bill — company
 * deliveries and store sales alike — to print or share on WhatsApp.
 */
class CompanyDeliveryController extends Controller
{
    public function __construct(private readonly RuleVersionService $rules) {}

    public function index(Request $request): Response
    {
        $deliveries = CompanyDelivery::with(['member.user', 'invoice'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (CompanyDelivery $delivery): array => [
                'id' => $delivery->id,
                'member' => ['customer_id' => $delivery->member->customer_id, 'name' => $delivery->member->user?->name],
                'item_name' => $delivery->item_name,
                'metal' => $delivery->metal,
                'weight' => number_format((float) $delivery->item_weight * $delivery->quantity, 3, '.', ''),
                'total_invoice_amount' => $delivery->total_invoice_amount,
                'delivered_at' => Dates::date($delivery->delivered_at),
                'invoice_no' => $delivery->invoice?->invoice_no,
            ]);

        return Inertia::render('super-admin/company-deliveries', [
            'deliveries' => $deliveries,
            'lookup' => $this->lookup($request->string('customer_id')->toString()),
            'gst_percent' => (float) $this->rules->value('store_gst_percent', 0),
        ]);
    }

    /**
     * What the delivery form needs for one member: whether they can receive their jewellery now, and at which rate.
     *
     * @return array<string, mixed>|null
     */
    private function lookup(string $customerId): ?array
    {
        if ($customerId === '') {
            return null;
        }

        $member = Member::with(['user', 'membershipPlan'])->where('customer_id', $customerId)->first();

        if ($member === null) {
            return ['customer_id' => $customerId, 'found' => false];
        }

        $plan = $member->membershipPlan;
        $schedule = $member->emiSchedule()->first();
        $metal = $plan?->product_category;
        $isCurrentRate = $schedule?->rate_booking_method === 'current_rate';
        $today = $metal ? PriceStoreSale::currentRate($metal) : null;
        $delivered = $member->productBenefits()->whereNotNull('delivered_at')->exists();
        $undelivered = $member->productBenefits()->whereNull('delivered_at')->exists();
        $unpaid = $schedule ? $schedule->installments()->where('status', '!=', 'paid')->count() : 0;

        $blocked = match (true) {
            $plan === null => 'This member has no plan.',
            $delivered && ! $undelivered => 'Plan jewellery has already been delivered.',
            $plan->isEmiPlan() && $unpaid > 0 => "Delivered after the last EMI — {$unpaid} EMI(s) still to pay.",
            ! $plan->isEmiPlan() && ! $undelivered => 'This member has no undelivered plan jewellery entitlement.',
            $today === null => "No {$metal} rate has been set yet.",
            default => null,
        };

        return [
            'customer_id' => $member->customer_id,
            'found' => true,
            'name' => $member->user?->name,
            'plan' => $plan?->name,
            'metal' => $metal,
            'booking' => $isCurrentRate ? 'current_rate' : ($plan?->isEmiPlan() ? 'future_rate' : 'one_time'),
            'entitled_weight_grams' => $isCurrentRate ? $schedule->fixed_weight_grams : null,
            'commitment_amount' => ! $isCurrentRate && $schedule ? $schedule->future_commitment_amount : null,
            'rate_per_gram' => $isCurrentRate ? $schedule->rate_per_gram_at_booking : $today?->rate_per_gram,
            'rate_is_locked' => $isCurrentRate,
            'making_charge_percent' => $today?->making_charge_percent,
            'blocked_reason' => $blocked,
        ];
    }

    public function store(Request $request, RecordCompanyDelivery $record): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'string', 'max:20'],
            'item_name' => ['required', 'string', 'max:255'],
            'item_weight' => ['required', 'numeric', 'min:0.001'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $member = Member::where('customer_id', $data['customer_id'])->first();

        if ($member === null) {
            throw ValidationException::withMessages(['customer_id' => 'No member found with this Customer ID.']);
        }

        $delivery = $record(
            $member,
            $data['item_name'],
            (float) $data['item_weight'],
            (int) $data['quantity'],
            $request->user(),
            SalesController::hallmarkInput($request),
        );

        return redirect()->route('super-admin.company-deliveries.invoice', $delivery)->with('bill_original', true);
    }

    public function invoice(CompanyDelivery $delivery): Response
    {
        abort_if(! $delivery->invoice()->exists(), 404);

        return Inertia::render('invoices/show', [
            'invoice' => InvoicePresenter::forCompanyDelivery($delivery, (bool) session('bill_original')),
            'back_url' => route('super-admin.company-deliveries.index'),
        ]);
    }

    /** Super Admin can open (print / share) any store's bill too — once the store has generated it. */
    public function storeSaleInvoice(StoreSale $sale): Response
    {
        abort_if(! $sale->invoice()->exists(), 404);

        return Inertia::render('invoices/show', [
            'invoice' => InvoicePresenter::forStoreSale($sale, false),
            'back_url' => route('super-admin.store-management.show', $sale->store_id),
        ]);
    }
}
