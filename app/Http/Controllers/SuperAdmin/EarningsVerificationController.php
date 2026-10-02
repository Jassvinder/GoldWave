<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\EarningsVerifier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin "Earnings Verification" — the same independent re-computation as `php artisan earnings:verify`, one click
 * away. Read-only: `?run=1` computes and shows the result, nothing is written or corrected.
 */
class EarningsVerificationController extends Controller
{
    public function index(Request $request, EarningsVerifier $verifier): Response
    {
        return Inertia::render('super-admin/earnings-verification', [
            'checks' => EarningsVerifier::CHECKS,
            'report' => $request->boolean('run') ? $verifier->run([], 100) : null,
        ]);
    }
}
