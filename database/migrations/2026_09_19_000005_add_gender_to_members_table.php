<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-122 (19-09-2026) — `members.gender` (male / female / other). Required at
 * registration going forward (`RegisterMemberRequest`), but the column itself
 * is nullable because every pre-existing member (and any company dummy
 * entry) has no gender on record; those render a neutral avatar (T-119)
 * until Super Admin sets one via the Member Edit dialog. Stored as a plain
 * string validated at the request layer, matching how other small value
 * sets on `members` are handled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('placeholder_name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
