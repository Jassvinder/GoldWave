<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\DummyEntries\AssignDummyEntryToLeader;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AssignDummyEntryRequest;
use App\Models\Member;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S05 — enter leader details into an available dummy entry. */
class DummyEntryAssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        $unassigned = Member::where('is_company_dummy', true)
            ->where('dummy_status', 'unassigned')
            ->orderBy('id')
            ->get()
            ->map(fn (Member $dummy): array => [
                'id' => $dummy->id,
                'customer_id' => $dummy->customer_id,
                'placeholder_name' => $dummy->placeholder_name,
                'placement_side' => $dummy->placement_side,
                'generated_at' => Dates::date($dummy->dummy_generated_at),
            ]);

        return Inertia::render('super-admin/dummy-entry-assignment', [
            'unassigned' => $unassigned,
        ]);
    }

    public function store(AssignDummyEntryRequest $request, AssignDummyEntryToLeader $action): RedirectResponse
    {
        $dummy = Member::findOrFail($request->integer('member_id'));

        $action(
            $dummy,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('mobile')->toString() ?: null,
            $request->user(),
        );

        return redirect()->route('super-admin.dummy-entry-assignment.index')->with('status', 'Leader assigned to dummy entry.');
    }
}
