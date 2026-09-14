<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Store\CreateStore;
use App\Actions\Store\ReassignStoreOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateStoreRequest;
use App\Http\Requests\SuperAdmin\ReassignStoreOwnerRequest;
use App\Http\Requests\SuperAdmin\UpdateStoreStatusRequest;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S09 / "Store list / management page (Super Admin)" — create/manage stores, assign Store Owners, allocation/advance, status. */
class StoreManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $stores = Store::with(['owner', 'wallet'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Store $store): array => $this->summarize($store));

        $unassignedAdmins = User::where('role', 'admin')
            ->whereDoesntHave('store')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('super-admin/store-management', [
            'stores' => $stores,
            'unassigned_admins' => $unassignedAdmins,
        ]);
    }

    public function store(CreateStoreRequest $request, CreateStore $action): RedirectResponse
    {
        $owner = $request->filled('owner_user_id') ? User::find($request->integer('owner_user_id')) : null;

        $action(
            $request->string('name')->toString(),
            $owner,
            $request->string('contact')->toString() ?: null,
            $request->string('location')->toString() ?: null,
            (float) $request->input('jewellery_allocation_value'),
            (float) $request->input('advance_amount'),
            $request->user(),
        );

        return redirect()->route('super-admin.store-management.index')->with('status', 'Store created.');
    }

    public function show(Request $request, Store $store): Response
    {
        $store->load(['owner', 'wallet']);

        $activity = StoreActivityLog::where('store_id', $store->id)
            ->with(['operator', 'affectedMember'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (StoreActivityLog $log): array => [
                'action_type' => $log->action_type,
                'operator_name' => $log->operator?->name,
                'affected_customer_id' => $log->affectedMember?->customer_id,
                'occurred_at' => Dates::date($log->occurred_at),
            ]);

        $recentSales = $store->sales()
            ->with('member')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'transaction_type' => $sale->transaction_type,
                'customer_id' => $sale->member?->customer_id,
                'item_name' => $sale->item_name,
                'total_invoice_amount' => $sale->total_invoice_amount,
                'status' => $sale->status,
                'created_at' => Dates::date($sale->created_at),
            ]);

        $unassignedAdmins = User::where('role', 'admin')
            ->whereDoesntHave('store')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('super-admin/store-detail', [
            'store' => $this->summarize($store),
            'activity' => $activity,
            'recent_sales' => $recentSales,
            'unassigned_admins' => $unassignedAdmins,
        ]);
    }

    public function reassignOwner(ReassignStoreOwnerRequest $request, Store $store, ReassignStoreOwner $action): RedirectResponse
    {
        $newOwner = User::findOrFail($request->integer('owner_user_id'));

        $action($store, $newOwner, $request->user());

        return redirect()->route('super-admin.store-management.show', $store)->with('status', 'Store owner reassigned.');
    }

    public function updateStatus(UpdateStoreStatusRequest $request, Store $store): RedirectResponse
    {
        $store->update(['status' => $request->string('status')->toString()]);

        return redirect()->route('super-admin.store-management.show', $store)->with('status', 'Store status updated.');
    }

    /** @return array<string, mixed> */
    private function summarize(Store $store): array
    {
        $walletBalance = $store->wallet !== null ? $store->wallet->balance : 0;

        return [
            'id' => $store->id,
            'name' => $store->name,
            'owner_name' => $store->owner?->name,
            'owner_user_id' => $store->owner_user_id,
            'contact' => $store->contact,
            'location' => $store->location,
            'status' => $store->status,
            'jewellery_allocation_value' => $store->jewellery_allocation_value,
            'advance_amount' => $store->advance_amount,
            'wallet_balance' => (string) $walletBalance,
        ];
    }
}
