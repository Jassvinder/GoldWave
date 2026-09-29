<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayContract;
use App\Contracts\SmsGatewayContract;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\RazorpayGateway;
use App\Services\Sms\LogSmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // T-137 — Razorpay is used as soon as both of its keys are configured; PAYMENT_GATEWAY=razorpay|fake is only an
        // explicit override. The fake gateway is a dev/test stand-in and is refused in production, so a forgotten key
        // can never silently fall back to a gateway that confirms fake payments.
        $this->app->bind(PaymentGatewayContract::class, function ($app): PaymentGatewayContract {
            $configured = filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
            $choice = config('services.payments.gateway') ?: ($configured ? 'razorpay' : 'fake');

            if ($choice === 'razorpay') {
                return $app->make(RazorpayGateway::class);
            }

            if ($app->isProduction()) {
                throw new RuntimeException('Online payments are not configured: set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET (the fake gateway is not allowed in production).');
            }

            return $app->make(FakePaymentGateway::class);
        });

        // T-139 — one SMS gateway for OTP and notifications. Only the `log` driver exists until an SMS provider is chosen;
        // a real provider is added as another `match` arm here (and needs DLT template ids for India).
        // 28-09-2026 (user decision): SMS is only really sent once a provider's API credentials are configured and
        // enabled in .env; until then — including a driver name nobody implemented yet — it keeps going to the log
        // (with a warning), so a half-finished SMS setup can never break OTP login or a notification.
        $this->app->bind(SmsGatewayContract::class, function ($app): SmsGatewayContract {
            $driver = (string) config('notifications.sms_driver');

            if ($driver !== 'log') {
                Log::warning("SMS_DRIVER \"{$driver}\" has no implementation yet — SMS is written to the log instead.");
            }

            return $app->make(LogSmsGateway::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
