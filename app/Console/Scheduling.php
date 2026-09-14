<?php

namespace App\Console;

use App\Jobs\EvaluateMonthlyPairMilestones;
use App\Jobs\ProcessBoosterPayouts;
use App\Jobs\ProcessEmiDueStatuses;
use App\Jobs\RunDailyDummyEntryGeneration;
use App\Jobs\RunDrawGroupGeneration;
use App\Jobs\RunMonthlyDrawExecution;
use Illuminate\Console\Scheduling\Schedule;

/**
 * DOMAIN_LOGIC.md §19's recurring jobs, registered here (called from
 * `bootstrap/app.php`'s `withSchedule`) rather than inline in that closure,
 * so the wiring itself is directly unit-testable against a plain `new
 * Schedule()` instance — resolving `Schedule::class` through the container
 * only fires this registration via Laravel's `Artisan::starting` mechanism,
 * which is unreliable to assert against directly in a test process that
 * runs multiple Artisan invocations (see `Docs/TASKS.md` T-019's note).
 *
 * Every entry runs `->withoutOverlapping()` (T-019's explicit "retry-safety"
 * requirement: a run still executing when the next tick fires must never
 * start a second concurrent copy) and an explicit `->timezone()` rather than
 * relying on any implicit default — this project already found a real bug
 * from an unset timezone once (T-003's `phpunit.xml` gap), so the
 * scheduler's timezone is never left to chance either. Each underlying Job's
 * `handle()` is independently idempotent (checked when each was built,
 * T-005/T-007/T-010/T-011/T-013), so a retried run after an overlap is also
 * always a safe no-op, not just a blocked one.
 */
class Scheduling
{
    public static function register(Schedule $schedule): void
    {
        $timezone = config('app.timezone');

        // §19 "Daily Company Direct Generator" — timing left as "exact time
        // TBD" in the source spec; 00:30 is an implementation choice (not a
        // business rule), run early so a day's dummy entries exist before
        // business hours.
        $schedule->job(new RunDailyDummyEntryGeneration)
            ->dailyAt('00:30')
            ->timezone($timezone)
            ->withoutOverlapping();

        // §8.1/§19 "Draw Group Generator" — 15th, 00:00.
        $schedule->job(new RunDrawGroupGeneration)
            ->monthlyOn(15, '00:00')
            ->timezone($timezone)
            ->withoutOverlapping();

        // §8.4/§19 "Monthly Draw Executor" — 15th, 12:00, after grouping.
        $schedule->job(new RunMonthlyDrawExecution)
            ->monthlyOn(15, '12:00')
            ->timezone($timezone)
            ->withoutOverlapping();

        // §5 item 7/§19 "EMI Due Processor" — daily, shortly after midnight
        // so today's due-date transitions land promptly.
        $schedule->job(new ProcessEmiDueStatuses)
            ->dailyAt('00:05')
            ->timezone($timezone)
            ->withoutOverlapping();

        // §7.3/§19 "Pair/Reward Monthly Evaluator" — month-end. Runs late on
        // the last calendar day so the month is effectively over;
        // `EvaluateMonthlyPairMilestones`'s `forMonth` defaults to the
        // current month when constructed with no argument, matching "the
        // month that's ending right now". Laravel recomputes
        // `lastDayOfMonth()`'s day-of-month fresh on every real
        // `schedule:run` invocation (a new process per cron tick, per
        // Laravel's own documented deployment model — see the deployment
        // note in `Docs/ARCHITECTURE.md`), so this correctly lands on the
        // 28th/29th/30th/31st as appropriate for whichever month it is.
        $schedule->job(new EvaluateMonthlyPairMilestones)
            ->lastDayOfMonth('23:30')
            ->timezone($timezone)
            ->withoutOverlapping();

        // §9.1 step 6/§19 "Booster Payout Processor" — daily, not monthly:
        // each `booster_payout_schedules` row's own `scheduled_date` follows
        // the member's individual activation-date-anniversary cadence
        // (T-011's pre-coding note), so only a daily check catches every
        // member's due date regardless of which day of the month they
        // activated on.
        $schedule->job(new ProcessBoosterPayouts)
            ->dailyAt('00:45')
            ->timezone($timezone)
            ->withoutOverlapping();

        // §19 "Report Export Worker" is explicitly "On demand" — dispatched
        // when a Super Admin requests an export (T-018's job to build), never
        // on a recurring cadence, so there is nothing to `schedule()` here.
        // The Job class itself doesn't exist yet (T-018 is still Pending);
        // no placeholder is registered for a Job that isn't built.
    }
}
