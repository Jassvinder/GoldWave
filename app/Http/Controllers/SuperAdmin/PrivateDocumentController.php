<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 02-10-2026 (T-206) — GPay/UPI payment screenshots and bank proof documents live on the private `local` disk and are
 * shown only through these routes, inside the company portal's `role:super_admin,admin` group. Before this they sat on
 * the public disk, where anyone with the file URL could open them without logging in.
 */
class PrivateDocumentController extends Controller
{
    public function paymentScreenshot(Payment $payment): StreamedResponse
    {
        return $this->inline($payment->upi_screenshot_path);
    }

    /** The member's latest bank detail row — the one Member Detail shows and Verify acts on. */
    public function bankProof(Member $member): StreamedResponse
    {
        return $this->inline($member->bankDetails()->latest('id')->value('proof_document_path'));
    }

    private function inline(?string $path): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
