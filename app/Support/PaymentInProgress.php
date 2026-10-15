<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * T-228/T-229 (15-10-2026) — marks a payment whose settlement runs in the background (Super Admin approval, store cash
 * collection, wallet-funded registration, online confirmation). While marked, the pages show it as "Processing…" and
 * refuse a second settlement or a rejection. The mark expires on its own after 10 minutes, so a lost job never leaves
 * a payment locked for good.
 */
class PaymentInProgress
{
    private const MINUTES = 10;

    /** Marks the payment; false when it is already being processed. */
    public static function claim(int $paymentId): bool
    {
        return Cache::add(self::key($paymentId), true, now()->addMinutes(self::MINUTES));
    }

    public static function isRunning(int $paymentId): bool
    {
        return Cache::has(self::key($paymentId));
    }

    public static function release(int $paymentId): void
    {
        Cache::forget(self::key($paymentId));
    }

    private static function key(int $paymentId): string
    {
        return "payment-in-progress:{$paymentId}";
    }
}
