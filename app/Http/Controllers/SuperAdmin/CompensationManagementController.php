<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\IncomeLedgerCalculation;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md's Admin Compensation Management. The config page is the
 * exact same L1-L12/pair/booster/store-distribution settings S03 already
 * owns — rather than a second divergent form and Action call site,
 * `config()` redirects straight to `RuleVersionController::index()`
 * (DOMAIN_LOGIC.md §21 T-017 pre-coding pass). Only the calculation audit
 * page is genuinely new here.
 */
class CompensationManagementController extends Controller
{
    public function config(Request $request): RedirectResponse
    {
        return redirect()->route('super-admin.rule-versions.index');
    }

    public function audit(Request $request): Response
    {
        $query = IncomeLedgerCalculation::query()->with(['beneficiary', 'ruleVersion']);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('customer_id')) {
            $customerId = $request->string('customer_id')->toString();
            $query->whereHas('beneficiary', fn ($q) => $q->where('customer_id', 'like', "%{$customerId}%"));
        }

        if ($request->filled('eligibility_status')) {
            $query->where('eligibility_status', $request->string('eligibility_status')->toString());
        }

        if ($request->filled('rule_version_id')) {
            $query->where('rule_version_id', $request->integer('rule_version_id'));
        }

        $calculations = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $calculations->through(fn (IncomeLedgerCalculation $calc): array => [
            'id' => $calc->id,
            'type' => $calc->type,
            'beneficiary_customer_id' => $calc->beneficiary?->customer_id,
            'rule_version_no' => $calc->ruleVersion->version_no,
            'level_no' => $calc->level_no,
            'rate_percent' => $calc->rate_percent,
            'amount' => $calc->amount,
            'eligibility_status' => $calc->eligibility_status,
            'skip_reason' => $calc->skip_reason,
            'created_at' => Dates::date($calc->created_at),
        ]);

        return Inertia::render('super-admin/compensation-audit', [
            'calculations' => $calculations,
            'filters' => $request->only(['type', 'customer_id', 'eligibility_status', 'rule_version_id']),
        ]);
    }
}
