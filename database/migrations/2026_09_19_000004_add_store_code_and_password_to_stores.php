<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * T-117 (19-09-2026) — user-requested dual login split: an Admin/Store Owner
 * logs in with a Store ID + Store password, never their email/password
 * (that path is now Super-Admin-only, `FortifyServiceProvider`), kept fully
 * separate from a Member's own Customer-ID login. `store_code` is generated
 * for every store, owner or not (it's the store's own permanent identifier);
 * `password` is only meaningful once a store has an owner to authenticate
 * as. Backfills every pre-existing store here rather than leaving them
 * without login credentials — mirrors the Customer ID convention (initial
 * password = the ID itself) as a memorable starting point; `CreateStore`/
 * `ReassignStoreOwner`/the new "Reset Store Password" control always set a
 * real chosen password going forward, never silently default to this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('store_code')->nullable()->unique()->after('name');
            $table->string('password')->nullable()->after('store_code');
        });

        $counter = DB::table('store_code_counters')->first();
        $nextValue = $counter->next_value;

        DB::table('stores')->orderBy('id')->get(['id', 'owner_user_id'])->each(function ($store) use (&$nextValue) {
            $storeCode = sprintf('GWLST%04d', $nextValue);
            $nextValue++;

            $updates = ['store_code' => $storeCode];

            if ($store->owner_user_id !== null) {
                $updates['password'] = Hash::make($storeCode);
            }

            DB::table('stores')->where('id', $store->id)->update($updates);
        });

        DB::table('store_code_counters')->where('id', $counter->id)->update(['next_value' => $nextValue]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['store_code', 'password']);
        });
    }
};
