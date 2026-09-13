<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your GoldWave login code');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.otp-code',
            with: ['code' => $this->code],
        );
    }
}
