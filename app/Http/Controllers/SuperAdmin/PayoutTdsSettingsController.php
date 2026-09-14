<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdatePayoutTdsSettingsRequest;
use App\Services\RuleVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S08 — minimum withdrawal, TDS percentage, payout processing settings. */
class PayoutTdsSettingsController extends Controller
{
    public function index(Request $request, RuleVersionService $rules): Response
    {
        return Inertia::render('super-admin/payout-tds-settings', [
            'payout_min_amount' => (float) $rules->value('payout_min_amount', 500),
            'payout_tds_percent' => (float) $rules->value('payout_tds_percent', 0),
            'payout_processing_fee_percent' => (float) $rules->value('payout_processing_fee_percent', 0),
        ]);
    }

    public function update(UpdatePayoutTdsSettingsRequest $request, PublishRuleVersion $action): RedirectResponse
    {
        $action([
            'payout_min_amount' => (float) $request->input('payout_min_amount'),
            'payout_tds_percent' => (float) $request->input('payout_tds_percent'),
            'payout_processing_fee_percent' => (float) $request->input('payout_processing_fee_percent'),
        ], $request->user());

        return redirect()->route('super-admin.payout-tds-settings.index')->with('status', 'Payout & TDS settings updated.');
    }
}
