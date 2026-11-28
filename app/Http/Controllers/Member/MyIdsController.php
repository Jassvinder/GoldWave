<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\AddMemberIdsRequest;
use App\Support\PersonIds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DOMAIN_LOGIC.md §3.4 (T-251) — My IDs: every ID the signed-in person holds, and "Add ID" for more (up to the limit),
 * placed anywhere in the tree, sponsored by their first ID, paid together by Cash or GPay/UPI.
 */
class MyIdsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_if($user->member === null, 404);

        return Inertia::render('member/my-ids', [
            'ids' => PersonIds::list($user),
            'add_form' => PersonIds::addForm($user),
        ]);
    }

    public function store(AddMemberIdsRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->member === null, 404);

        return PersonIds::add($user, $request, $user);
    }
}
