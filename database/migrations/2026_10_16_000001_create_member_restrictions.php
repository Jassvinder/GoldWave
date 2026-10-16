<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T-231 (DOMAIN_LOGIC.md §2.3, 16-10-2026) — Payout Hold and Account Block.
     *
     * `members.payout_hold_at` / `members.blocked_at` hold the current state (null = not restricted), so every
     * earnings and login check reads the member row it already has. `member_restrictions` keeps the full history:
     * who applied and lifted each restriction, when, and why. A Booster payout that falls inside a block is recorded
     * as `skipped` and never paid.
     *
     * A separate migration, not merged into the create migrations (08-10-2026 rule): the live database is in use, so
     * schema changes are forward-only from here.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('payout_hold_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
        });

        Schema::create('member_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->enum('type', ['payout_hold', 'account_block']);
            $table->text('reason');
            $table->timestamp('started_at');
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users');
            $table->text('end_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'type', 'ended_at']);
        });

        $this->boosterStatuses(['pending', 'paid', 'skipped']);
    }

    public function down(): void
    {
        DB::table('booster_payout_schedules')->where('status', 'skipped')->update(['status' => 'pending']);
        $this->boosterStatuses(['pending', 'paid']);

        Schema::dropIfExists('member_restrictions');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['payout_hold_at', 'blocked_at']);
        });
    }

    /**
     * Postgres keeps an enum as a CHECK constraint, and `->change()` on an enum emits invalid SQL there, so it is
     * replaced by hand. SQLite (tests) rebuilds the column through the schema builder.
     *
     * @param  list<string>  $statuses
     */
    private function boosterStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $status): string => "'{$status}'", $statuses));

            DB::statement('ALTER TABLE booster_payout_schedules DROP CONSTRAINT IF EXISTS booster_payout_schedules_status_check');
            DB::statement("ALTER TABLE booster_payout_schedules ADD CONSTRAINT booster_payout_schedules_status_check CHECK (status IN ({$list}))");

            return;
        }

        Schema::table('booster_payout_schedules', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('pending')->change();
        });
    }
};
