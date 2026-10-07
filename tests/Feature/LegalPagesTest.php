<?php

use App\Http\Controllers\LegalPageController;

/** T-207 (02-10-2026) — public legal pages, reachable without login, with the Super-Admin-editable contact details. */
test('the legal pages are public and carry the contact details and last-updated date', function (string $url, string $component) {
    $this->seed();

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component($component)
        ->where('last_updated', LegalPageController::LAST_UPDATED)
        ->has('contact.phone')
        ->has('contact.email'));
})->with([
    'terms' => ['/terms', 'legal/terms'],
    'privacy' => ['/privacy', 'legal/privacy'],
    'refund' => ['/refund-policy', 'legal/refund-policy'],
]);
