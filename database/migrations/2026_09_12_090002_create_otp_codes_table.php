<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DOMAIN_LOGIC.md §2.2: OTP is required for member login (method 1) and is
     * the sole gate for password set/reset (method 2 cannot bypass it). One
     * `identifier` (mobile or email — whichever the member used) can have
     * multiple historical codes; only the newest unconsumed, unexpired one is
     * ever valid (see `OtpService`). `purpose` keeps a login OTP separate from
     * a password-reset OTP so completing one never satisfies the other.
     */
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('identifier'); // mobile or email, as supplied by the member
            $table->enum('channel', ['sms', 'email']);
            $table->enum('purpose', ['login', 'password_reset']);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['identifier', 'purpose', 'consumed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
