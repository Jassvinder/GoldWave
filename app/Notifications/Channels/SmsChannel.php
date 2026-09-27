<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGatewayContract;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Notification;

/** Laravel notification channel for SMS: hands the notification's `toSms()` text to the bound `SmsGatewayContract`. */
class SmsChannel
{
    public function __construct(private readonly SmsGatewayContract $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof AppNotification || ! $notifiable instanceof User || blank($notifiable->mobile)) {
            return;
        }

        $this->sms->send($notifiable->mobile, $notification->toSms($notifiable), $notification->key());
    }
}
