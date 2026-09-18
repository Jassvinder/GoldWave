<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Admin\CreateAdminUser;
use App\Actions\Admin\FindMemberEligibleForAdminPromotion;
use App\Actions\Admin\UpdateAdminUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateAdminUserRequest;
use App\Http\Requests\SuperAdmin\FindMemberForAdminRequest;
use App\Http\Requests\SuperAdmin\UpdateAdminUserRequest;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S02 — create/manage Admin users; "access boundaries" = which store (if any) they own. */
class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $admins = User::where('role', 'admin')
            ->with(['store', 'member'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'customer_id' => $user->member?->customer_id,
                'created_at' => Dates::date($user->created_at),
                'store' => $user->store ? [
                    'id' => $user->store->id,
                    'name' => $user->store->name,
                    'status' => $user->store->status,
                ] : null,
            ]);

        return Inertia::render('super-admin/admin-users', [
            'admins' => $admins,
        ]);
    }

    public function findMember(FindMemberForAdminRequest $request, FindMemberEligibleForAdminPromotion $action): JsonResponse
    {
        try {
            $member = $action($request->string('customer_id')->toString());
        } catch (ValidationException $e) {
            return response()->json(['valid' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'valid' => true,
            'member_name' => $member->user?->name,
            'member_customer_id' => $member->customer_id,
        ]);
    }

    public function store(CreateAdminUserRequest $request, FindMemberEligibleForAdminPromotion $find, CreateAdminUser $action): RedirectResponse
    {
        $member = $find($request->string('customer_id')->toString());

        $action($member);

        return redirect()->route('super-admin.admin-users.index')->with('status', 'Member promoted to Admin.');
    }

    public function update(UpdateAdminUserRequest $request, User $admin_user, UpdateAdminUser $action): RedirectResponse
    {
        $action(
            $admin_user,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('mobile')->toString() ?: null,
        );

        return redirect()->route('super-admin.admin-users.index')->with('status', 'Admin user updated.');
    }
}
