<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Admin\CreateAdminUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateAdminUserRequest;
use App\Models\Store;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S02 — create/manage Admin users; "access boundaries" = which store (if any) they own. */
class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $admins = User::where('role', 'admin')
            ->with('store')
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
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

    public function store(CreateAdminUserRequest $request, CreateAdminUser $action): RedirectResponse
    {
        $action(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()->route('super-admin.admin-users.index')->with('status', 'Admin user created.');
    }
}
