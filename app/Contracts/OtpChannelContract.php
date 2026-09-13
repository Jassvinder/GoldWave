<?php

namespace App\Contracts;

interface OtpChannelContract
{
    public function send(string $identifier, string $code): void;
}
