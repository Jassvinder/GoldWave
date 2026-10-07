<?php

namespace App\Http\Controllers;

use App\Services\RuleVersionService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-207 (02-10-2026) — public legal pages: Terms & Conditions, Privacy Policy, Refund & Cancellation Policy. The text
 * lives in the page components; the contact block reuses the Super-Admin-editable landing page contact details.
 * GoldWave has no refund and no return anywhere (`Docs/DOMAIN_LOGIC.md` §0, §16.7) — the refund page states exactly that.
 */
class LegalPageController extends Controller
{
    /** Shown on every legal page; change it whenever the text of any page changes. */
    public const LAST_UPDATED = '02-10-2026';

    public function terms(RuleVersionService $rules): Response
    {
        return $this->render('legal/terms', $rules);
    }

    public function privacy(RuleVersionService $rules): Response
    {
        return $this->render('legal/privacy', $rules);
    }

    public function refund(RuleVersionService $rules): Response
    {
        return $this->render('legal/refund-policy', $rules);
    }

    private function render(string $component, RuleVersionService $rules): Response
    {
        return Inertia::render($component, [
            'contact' => WelcomeController::contact($rules),
            'last_updated' => self::LAST_UPDATED,
        ]);
    }
}
