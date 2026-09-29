<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\CreateStore;
use App\Actions\Store\PriceStoreSale;
use App\Actions\Store\ReassignStoreOwner;
use App\Actions\Store\ResetStorePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AllocateStoreInventoryRequest;
use App\Http\Requests\SuperAdmin\CreateStoreRequest;
use App\Http\Requests\SuperAdmin\ReassignStoreOwnerRequest;
use App\Http\Requests\SuperAdmin\ResetStorePasswordRequest;
use App\Http\Requests\SuperAdmin\UpdateStoreStatusRequest;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\StoreInventoryItem;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $password = $owner !== null
            ? ($request->input('password_mode') === 'manual' ? $request->string('password')->toString() : Str::password(12))
            : null;

        $store = $action(
            $request->string('name')->toString(),
            $owner,
            $request->string('contact')->toString() ?: null,
            $request->string('location')->toString() ?: null,
            (float) $request->input('jewellery_allocation_value'),
            (float) $request->input('advance_amount'),
            $request->user(),
            $password,
        );

        $status = $password !== null
            ? "Store created. Store ID: {$store->store_code} — Password: {$password} (save this now, it won't be shown again)."
            : "Store created. Store ID: {$store->store_code}.";

        return redirect()->route('super-admin.store-management.index')->with('status', $status);
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
            ->with(['member', 'invoice'])
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
                'invoice_no' => $sale->invoice?->invoice_no,
            ]);

        $unassignedAdmins = User::where('role', 'admin')
            ->whereDoesntHave('store')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $inventory = $store->inventoryItems()
            ->orderBy('item_name')
            ->get()
            ->map(fn (StoreInventoryItem $item): array => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'metal' => $item->metal,
                'weight' => $item->weight,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'description' => $item->description,
            ]);

        return Inertia::render('super-admin/store-detail', [
            'store' => $this->summarize($store),
            'activity' => $activity,
            'recent_sales' => $recentSales,
            'unassigned_admins' => $unassignedAdmins,
            'inventory' => $inventory,
        ]);
    }

    /** DOMAIN_LOGIC.md §16.5 — Super Admin's jewellery allocation to a store's item-wise inventory, new store or existing. */
    public function allocateInventory(AllocateStoreInventoryRequest $request, Store $store, AllocateStoreInventoryItem $action): RedirectResponse
    {
        $metal = $request->string('metal')->toString();
        $weight = (float) $request->input('weight');

        // T-169 (28-09-2026, user decision) — the store's stock is valued at the current rate, picked up
        // automatically: price per piece = weight × today's rate (making is added only when it is sold).
        $rate = PriceStoreSale::currentRate($metal);

        if ($rate === null) {
            throw ValidationException::withMessages([
                'metal' => "No {$metal} rate has been set yet — enter today's rate first.",
            ]);
        }

        $action(
            $store,
            $request->string('item_name')->toString(),
            $metal,
            $weight,
            $request->integer('quantity'),
            round($weight * (float) $rate->rate_per_gram, 2),
            $request->user(),
            $request->string('description')->toString() ?: null,
        );

        return redirect()->route('super-admin.store-management.show', $store)
            ->with('status', 'Inventory added.');
    }

    public function reassignOwner(ReassignStoreOwnerRequest $request, Store $store, ReassignStoreOwner $action): RedirectResponse
    {
        $newOwner = User::findOrFail($request->integer('owner_user_id'));

        // T-133 — always auto-generated and shown once; the Super Admin's own password was already checked by the request.
        $password = Str::password(12);

        $action($store, $newOwner, $request->user(), $password);

        return redirect()->route('super-admin.store-management.show', $store)
            ->with('status', "Store owner reassigned. New Store password: {$password} (save this now, it won't be shown again).");
    }

    public function resetPassword(ResetStorePasswordRequest $request, Store $store, ResetStorePassword $action): RedirectResponse
    {
        $password = $request->input('password_mode') === 'manual' ? $request->string('password')->toString() : Str::password(12);

        $action($store, $password, $request->user());

        return redirect()->route('super-admin.store-management.show', $store)
            ->with('status', "Store password reset. New password: {$password} (save this now, it won't be shown again).");
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
            'store_code' => $store->store_code,
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
