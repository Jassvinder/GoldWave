<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\DummyEntries\InsertEntryUnderRoot;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-174 — the deliberately low-key "Maintenance" page (Super Admin only, DOMAIN_LOGIC.md §2): one plain link that
 * inserts an entry directly under the company root on the chosen side.
 */
class MaintenanceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('super-admin/maintenance');
    }

    public function store(Request $request, InsertEntryUnderRoot $action): RedirectResponse
    {
        $validated = $request->validate(['side' => ['required', 'in:left,right']]);

        $entry = $action($validated['side']);

        return redirect()->route('super-admin.maintenance.index')->with('status', "Done — {$entry->customer_id}.");
    }
}
