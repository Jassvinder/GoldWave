<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentModes;
use App\Services\RuleVersionService;
use App\Support\WebpImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-196 (30-09-2026) — the company's GPay/UPI details shown on every payment step (UPI ID + QR image), published as
 * a rule version like every other setting. Also shows whether Razorpay ("Online") is switched on.
 */
class PaymentSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('super-admin/payment-settings', [
            'settings' => PaymentModes::forPage(),
            'online_enabled' => PaymentModes::onlineEnabled(),
        ]);
    }

    public function update(Request $request, PublishRuleVersion $publish, RuleVersionService $rules): RedirectResponse
    {
        $validated = $request->validate([
            'upi_id' => ['required', 'string', 'max:100', 'regex:/^[\w.\-]{2,}@[a-zA-Z]{2,}$/'],
            'upi_qr' => [$rules->value('company_upi_qr_path') ? 'nullable' : 'required', 'image', 'max:5120'],
        ], ['upi_id.regex' => 'Enter a valid UPI ID, e.g. goldwave@okaxis.']);

        $values = ['company_upi_id' => trim($validated['upi_id'])];

        if ($request->hasFile('upi_qr')) {
            $path = WebpImageStore::store($request->file('upi_qr'), 'payment-qr');

            if ($path === false) {
                throw ValidationException::withMessages(['upi_qr' => 'The QR image could not be stored.']);
            }

            $values['company_upi_qr_path'] = $path;
        }

        $publish($values, $request->user(), 'Payment settings (UPI) updated');

        return redirect()->route('super-admin.payment-settings.index')->with('status', 'Payment settings saved.');
    }
}
