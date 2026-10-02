<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * T-140 — base class of every GoldWave notification. A subclass says what it is (`key`, `category`), what it says
 * (`title`, `body`) and where it leads (`url`); this class turns that into the bell/Notifications-page record, an email
 * and an SMS, and picks the channels from `config/notifications.php` (dropping email/SMS when the recipient has no
 * address / mobile). Always sent through `App\Services\Notifier`, which makes sure a failing channel never breaks the
 * action that triggered the notification.
 *
 * Categories drive the tabs on the Notifications page: `payment`, `request`, `emi`.
 */
abstract class AppNotification extends Notification
{
    /** Config key under `notifications.channels`. */
    abstract public function key(): string;

    abstract public function category(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    /** Internal path (starting with "/") the notification leads to, or null. */
    public function url(): ?string
    {
        return null;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = [];

        foreach ((array) config("notifications.channels.{$this->key()}", ['database']) as $channel) {
            $available = match ($channel) {
                'database' => true,
                'mail' => (bool) config('notifications.mail_enabled') && filled($notifiable->email ?? null),
                'sms' => (bool) config('notifications.sms_enabled') && filled($notifiable->mobile ?? null),
                default => false,
            };

            if ($available) {
                $channels[] = $channel === 'sms' ? SmsChannel::class : $channel;
            }
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->key(),
            'category' => $this->category(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title().' — GoldWave')
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line($this->body());

        if ($this->url() !== null) {
            $mail->action('Open in GoldWave', url($this->url()));
        }

        return $mail->salutation('GoldWave');
    }

    public function toSms(object $notifiable): string
    {
        return $this->title().': '.$this->body().' -GoldWave';
    }
}
