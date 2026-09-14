<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Store;
use App\Models\StoreActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * DOMAIN_LOGIC.md §16.3 — Super Admin must be able to review a complete
 * activity/audit log per store: which store, which Admin/Store Owner
 * performed the action, exact date/time, action type, affected member,
 * transaction/reference ID. This is the ONE place every Store Action writes
 * a `store_activity_logs` row, mirroring `WalletLedgerService`'s exclusivity
 * rule for `wallet_ledger_entries`.
 *
 * **Discovered during T-016's pre-coding pass, fixed here, not a business-
 * rule change:** every T-014 Store Action (`CreateStore`, `ConfirmStoreSale`,
 * `RecordItemBuyback`, `RecordStoreWalletTopup`, `RecordPlanJewelleryDelivery`,
 * `AllocateStoreInventoryItem`) already had the `store_activity_logs` table
 * available (T-002's up-front schema pass) but none of them ever wrote to
 * it — §16.3's audit-log requirement had never actually been satisfied by
 * any shipped code. T-016 is the first task whose pages actually need this
 * log to show anything, so it's fixed at the source (every Store Action
 * itself) rather than reconstructed after the fact from other tables.
 */
class StoreActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValue
     * @param  array<string, mixed>|null  $newValue
     */
    public function record(
        Store $store,
        User $operator,
        string $actionType,
        ?Member $affectedMember,
        ?Model $affectedReference,
        ?array $oldValue = null,
        ?array $newValue = null,
    ): StoreActivityLog {
        return StoreActivityLog::create([
            'store_id' => $store->id,
            'operator_user_id' => $operator->id,
            'action_type' => $actionType,
            'affected_member_id' => $affectedMember?->id,
            'affected_reference_type' => $affectedReference?->getMorphClass(),
            'affected_reference_id' => $affectedReference?->getKey(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'occurred_at' => now(),
        ]);
    }
}
