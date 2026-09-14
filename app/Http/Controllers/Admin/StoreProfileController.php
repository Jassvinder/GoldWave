<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStoreProfileRequest;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md A02 — assigned store details and permitted store-level
 * settings. DOMAIN_LOGIC.md §17.2 reserves store creation, ownership,
 * status, and Store Wallet funding/allocation to Super Admin — the only
 * fields an Admin/Store Owner may edit here are the cosmetic `contact`/
 * `location` details, everything else on this page is read-only.
 */
class StoreProfileController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $store->load('wallet');
        $walletBalance = $store->wallet !== null ? $store->wallet->balance : 0;

        return Inertia::render('admin/profile', [
            'store' => [
                'name' => $store->name,
                'contact' => $store->contact,
                'location' => $store->location,
                'status' => $store->status,
                'jewellery_allocation_value' => $store->jewellery_allocation_value,
                'advance_amount' => $store->advance_amount,
                'wallet_balance' => (string) $walletBalance,
            ],
        ]);
    }

    public function update(UpdateStoreProfileRequest $request): RedirectResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $store->update([
            'contact' => $request->string('contact')->toString() ?: null,
            'location' => $request->string('location')->toString() ?: null,
        ]);

        return redirect()->route('admin.profile.show')->with('status', 'Store profile updated.');
    }
}
