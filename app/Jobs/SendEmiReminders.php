<?php

namespace App\Jobs;

use App\Models\EmiReminderLog;
use App\Models\EmiSchedule;
use App\Notifications\EmiDueReminder;
use App\Services\Notifier;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * T-142 / DOMAIN_LOGIC.md §21 "Notifications, SMS and reminders" — reminds an active member about their **next payable**
 * installment (the earliest unpaid one; later installments cannot be paid ahead anyway, §5 item 8): from 3 days before
 * its due date (`upcoming`), on the due date (`due`), and once it is overdue — the day after, then every 7 days
 * (`overdue_1`, `overdue_8`, `overdue_15`, …). Each (installment, kind) is logged in `emi_reminder_logs` before sending, so
 * running the job again — or after a missed day — never reminds twice. A member with no user account is skipped, and a
 * failing email/SMS never stops the run (`Notifier`).
 */
class SendEmiReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $today = now()->startOfDay();

        EmiSchedule::query()
            ->whereHas('member', fn ($query) => $query->where('status', 'active'))
            ->with('member.user')
            ->chunkById(200, function ($schedules) use ($today): void {
                foreach ($schedules as $schedule) {
                    $this->remind($schedule, $today);
                }
            });
    }

    private function remind(EmiSchedule $schedule, CarbonInterface $today): void
    {
        $user = $schedule->member?->user;

        if ($user === null) {
            return;
        }

        $next = $schedule->installments()->where('status', '!=', 'paid')->orderBy('installment_no')->first();

        if ($next === null) {
            return;
        }

        // Positive = days still to go, 0 = due today, negative = overdue.
        $daysToDue = (int) round($today->diffInDays(Carbon::parse($next->due_date)->startOfDay(), false));

        if ($daysToDue > 3) {
            return;
        }

        if ($daysToDue >= 1) {
            [$kind, $logKind] = ['upcoming', 'upcoming'];
        } elseif ($daysToDue === 0) {
            [$kind, $logKind] = ['due', 'due'];
        } else {
            $milestone = 1 + 7 * intdiv(-$daysToDue - 1, 7);
            [$kind, $logKind] = ['overdue', "overdue_{$milestone}"];
        }

        $log = EmiReminderLog::firstOrCreate(
            ['emi_installment_id' => $next->id, 'kind' => $logKind],
            ['sent_at' => now()],
        );

        if ($log->wasRecentlyCreated) {
            Notifier::toUser($user, new EmiDueReminder($next, $kind));
        }
    }
}
