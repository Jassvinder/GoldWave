<?php

namespace App\Notifications;

use App\Models\EmiInstallment;
use App\Support\Dates;

/**
 * Member: reminder for their next payable EMI installment. `$kind` is `upcoming` (3 days before), `due` (the due date),
 * or `overdue` (1 day after, then every 7 days) — see `App\Jobs\SendEmiReminders`.
 */
class EmiDueReminder extends AppNotification
{
    public function __construct(public readonly EmiInstallment $installment, public readonly string $kind) {}

    public function key(): string
    {
        return 'emi_due_reminder';
    }

    public function category(): string
    {
        return 'emi';
    }

    public function title(): string
    {
        return match ($this->kind) {
            'upcoming' => 'EMI due in 3 days',
            'due' => 'EMI due today',
            default => 'EMI overdue',
        };
    }

    public function body(): string
    {
        $amount = '₹'.number_format((float) $this->installment->amount, 2);
        $due = Dates::display($this->installment->due_date);
        $no = $this->installment->installment_no;

        return match ($this->kind) {
            'upcoming' => "EMI #{$no} of {$amount} is due on {$due}. Pay it from the EMI Schedule page.",
            'due' => "EMI #{$no} of {$amount} is due today ({$due}). Pay it from the EMI Schedule page.",
            default => "EMI #{$no} of {$amount} was due on {$due} and is still unpaid. Please pay it from the EMI Schedule page.",
        };
    }

    public function url(): string
    {
        return '/member/emi';
    }
}
