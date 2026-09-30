<?php

namespace App\Notifications;

use App\Models\MemberBankDetail;

/** Member (T-195): their bank details were verified, so payout requests are open now. */
class BankDetailsVerified extends AppNotification
{
    public function __construct(public readonly MemberBankDetail $bankDetail) {}

    public function key(): string
    {
        return 'bank_details_verified';
    }

    public function category(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'Bank details verified';
    }

    public function body(): string
    {
        $account = $this->bankDetail->bank_name.' ···'.substr((string) $this->bankDetail->account_number, -4);

        return "Your bank details ({$account}) are verified. You can now request a payout from your wallet.";
    }

    public function url(): string
    {
        return '/member/payout';
    }
}
