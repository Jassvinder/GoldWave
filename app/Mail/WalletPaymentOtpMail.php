<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** DOMAIN_LOGIC.md §12.2(d) — T-215. The code that approves paying a new member's registration from the payer's wallet. */
class WalletPaymentOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly ?string $newMemberName,
        public readonly ?string $planName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your GoldWave wallet payment code');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.wallet-payment-otp',
            with: [
                'code' => $this->code,
                'newMemberName' => $this->newMemberName,
                'planName' => $this->planName,
            ],
        );
    }
}
