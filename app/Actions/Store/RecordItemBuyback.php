<?php

namespace App\Actions\Store;

use App\Models\Member;
use App\Models\MetalRate;
use App\Models\Store;
use App\Models\StoreBuyback;
use App\Models\User;
use App\Services\RuleVersionService;
use App\Services\StoreActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §16.7 — the store buying back a previously-sold item from
 * the member who owns it, at a versioned percentage of the item's *current*
 * market rate (never the original sale price). The bought-back item returns
 * to the store's inventory as new stock (§16.5). Deliberately never touches
 * `store_profit_distributions`/`income_ledger_calculations` (§16.9) — this
 * is the reverse of a sale, outside both rules' scope. Payment happens
 * outside the app (§17.4); only the transaction itself is recorded here.
 */
class RecordItemBuyback
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly StoreActivityLogger $activityLog,
    ) {}

    public function __invoke(
        Store $store,
        Member $member,
        string $itemName,
        string $metal,
        float $weight,
        int $quantity,
        ?string $description,
        MetalRate $currentMetalRate,
        User $operator,
    ): StoreBuyback {
        $ruleVersion = $this->rules->activeVersion();

        if (! $ruleVersion) {
            throw ValidationException::withMessages([
                'rule_version' => 'No active rule version is configured — cannot price this buyback.',
            ]);
        }

        $buybackPercent = (float) $this->rules->value('item_buyback_percent', 60);
        $ratePerGram = (float) $currentMetalRate->rate_per_gram;
        $metalValue = round($weight * $quantity * $ratePerGram, 2);
        $pricePaid = round($metalValue * $buybackPercent / 100, 2);

        return DB::transaction(function () use (
            $store, $member, $itemName, $metal, $weight, $quantity, $description,
            $currentMetalRate, $ratePerGram, $buybackPercent, $pricePaid, $ruleVersion, $operator,
        ) {
            $inventoryItem = $store->inventoryItems()->create([
                'item_name' => $itemName,
                'metal' => $metal,
                'weight' => $weight,
                'quantity' => $quantity,
                'price' => $ratePerGram * $weight,
                'description' => $description,
            ]);

            $buyback = StoreBuyback::create([
                'store_id' => $store->id,
                'member_id' => $member->id,
                'item_name' => $itemName,
                'metal' => $metal,
                'weight' => $weight,
                'quantity' => $quantity,
                'description' => $description,
                'metal_rate_id' => $currentMetalRate->id,
                'rate_per_gram_at_buyback' => $ratePerGram,
                'buyback_percent' => $buybackPercent,
                'price_paid' => $pricePaid,
                'resulting_inventory_item_id' => $inventoryItem->id,
                'rule_version_id' => $ruleVersion->id,
                'occurred_at' => now(),
            ]);

            $this->activityLog->record($store, $operator, 'item_buyback', $member, $buyback);

            return $buyback;
        });
    }
}
