<?php

namespace App\Services\Otp;

use App\Contracts\OtpChannelContract;
use App\Mail\OtpCodeMail;
use Illuminate\Support\Facades\Mail;

class EmailOtpChannel implements OtpChannelContract
{
    public function send(string $identifier, string $code): void
    {
        Mail::to($identifier)->send(new OtpCodeMail($code));
    }
}
