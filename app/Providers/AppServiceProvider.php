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
        $this->app->bind(SmsGatewayContract::class, function ($app): SmsGatewayContract {
            return match ((string) config('notifications.sms_driver')) {
                'log' => $app->make(LogSmsGateway::class),
                default => throw new RuntimeException('Unknown SMS_DRIVER "'.config('notifications.sms_driver').'" — only "log" exists until an SMS provider is added.'),
            };
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
