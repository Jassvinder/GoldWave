<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\RuleVersionService;
use App\Support\WebpImageStore;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * T-196 (DOMAIN_LOGIC.md §10, 30-09-2026) — the one place that knows which payment modes are offered and which need
 * a person to approve them:
 * - `cash` and `upi` (GPay/UPI to the company QR, with Ref ID + screenshot) wait for Super Admin / Admin approval;
 * - `wallet` (a Store Admin's Store Wallet or a logged-in member's own wallet) is confirmed at once;
 * - `online` (Razorpay) is offered only while `services.payments.online_enabled` is on — off by default.
 */
class PaymentModes
{
    /** Modes that wait for a Super Admin / Admin approval (`payments.cash_status`). */
    public const NEEDS_APPROVAL = ['cash', 'upi'];

    /** @return list<string> modes a member or registrant may choose; `wallet` only on the assisted flows. */
    public static function offered(bool $withWallet = false): array
    {
        $modes = ['cash', 'upi'];

        if (self::onlineEnabled()) {
            $modes[] = 'online';
        }

        if ($withWallet) {
            $modes[] = 'wallet';
        }

        return $modes;
    }

    public static function onlineEnabled(): bool
    {
        return (bool) config('services.payments.online_enabled', false);
    }

    public static function needsApproval(string $mode): bool
    {
        return in_array($mode, self::NEEDS_APPROVAL, true);
    }

    /** Initial `cash_status` for a new payment in this mode. */
    public static function initialApprovalStatus(string $mode): ?string
    {
        return self::needsApproval($mode) ? 'pending_verification' : null;
    }

    /**
     * Validation rules for the GPay/UPI proof, keyed by the request field that holds the mode.
     *
     * T-219 (13-10-2026): for any other mode the proof fields are dropped, not validated — a Ref ID typed under
     * GPay/UPI before switching to Cash used to fail `min:6` with the field hidden, so Submit seemed to do nothing.
     *
     * @return array<string, mixed>
     */
    public static function upiProofRules(string $modeField): array
    {
        return [
            'upi_reference' => ["exclude_unless:{$modeField},upi", 'required', 'string', 'min:6', 'max:64', 'unique:payments,upi_reference'],
            'upi_screenshot' => ["exclude_unless:{$modeField},upi", 'required', 'image', 'max:5120'],
        ];
    }

    /**
     * What a payment step shows: the modes to offer and the company's GPay/UPI details (Super Admin → Payment
     * Settings; rule keys `company_upi_id`, `company_upi_qr_path`). Sent as the page prop `payment_options`.
     *
     * @return array{modes: list<string>, upi_id: string|null, upi_qr_url: string|null}
     */
    public static function forPage(bool $withWallet = false): array
    {
        $rules = app(RuleVersionService::class);
        $qrPath = $rules->value('company_upi_qr_path');

        return [
            'modes' => self::offered($withWallet),
            'upi_id' => $rules->value('company_upi_id') ?: null,
            'upi_qr_url' => self::qrUrl($qrPath),
        ];
    }

    /**
     * T-212 (06-10-2026) — the company QR is either a file the Super Admin uploaded (a `public` disk path such as
     * `payment-qr/…webp`) or the seeded default, a site asset given as an absolute path (`/Images/QRcode.webp`).
     */
    private static function qrUrl(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return str_starts_with($path, '/') ? asset(ltrim($path, '/')) : Storage::disk('public')->url($path);
    }

    /** Stores the Ref ID and screenshot on a `upi` payment (no-op for other modes). */
    public static function attachUpiProof(?Payment $payment, Request $request): void
    {
        if ($payment === null || $payment->mode !== 'upi') {
            return;
        }

        $screenshot = $request->file('upi_screenshot');
        // Private disk, like bank proof documents — viewed only through PrivateDocumentController (02-10-2026).
        $path = $screenshot instanceof UploadedFile ? WebpImageStore::store($screenshot, 'payment-proofs', 'local') : false;

        $payment->update([
            'upi_reference' => trim((string) $request->input('upi_reference')),
            'upi_screenshot_path' => $path === false ? null : $path,
        ]);
    }
}
