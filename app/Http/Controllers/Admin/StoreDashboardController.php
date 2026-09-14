<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md A01 — assigned store sales, repurchases, inventory status, owner share, distributions, alerts. */
class StoreDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $store->load('wallet');
        $walletBalance = $store->wallet !== null ? $store->wallet->balance : 0;

        $salesCount = StoreSale::where('store_id', $store->id)->where('status', 'confirmed')->count();
        $salesTotal = StoreSale::where('store_id', $store->id)->where('status', 'confirmed')->sum('sale_amount');
        $repurchaseCount = StoreSale::where('store_id', $store->id)->where('transaction_type', 'repurchase')->count();
        $inventoryItemCount = $store->inventoryItems()->count();
        $inventoryUnitTotal = (int) $store->inventoryItems()->sum('quantity');

        $ownerMember = $store->ownerMember();
        $ownerShare = $ownerMember
            ? StoreProfitDistribution::whereHas('storeSale', fn ($q) => $q->where('store_id', $store->id))
                ->where('beneficiary_type', 'store_owner')
                ->sum('amount')
            : 0;

        $pendingDistributionCount = StoreSale::where('store_id', $store->id)->where('distribution_status', 'pending')->count();

        $alerts = [];

        if ((float) $walletBalance <= 0) {
            $alerts[] = 'Store Wallet balance is empty — store-initiated payments cannot be processed until topped up.';
        }

        if ($pendingDistributionCount > 0) {
            $alerts[] = "{$pendingDistributionCount} confirmed sale(s) awaiting distribution processing.";
        }

        return Inertia::render('admin/dashboard', [
            'store' => [
                'name' => $store->name,
                'status' => $store->status,
                'location' => $store->location,
            ],
            'wallet_balance' => (string) $walletBalance,
            'sales' => [
                'count' => $salesCount,
                'total' => (string) $salesTotal,
                'repurchase_count' => $repurchaseCount,
            ],
            'inventory' => [
                'item_count' => $inventoryItemCount,
                'unit_total' => $inventoryUnitTotal,
            ],
            'owner_share' => (string) $ownerShare,
            'alerts' => $alerts,
        ]);
    }
}
