<?php

use App\Console\Scheduling;
use App\Jobs\EvaluateMonthlyPairMilestones;
use App\Jobs\ProcessBoosterPayouts;
use App\Jobs\ProcessEmiDueStatuses;
use App\Jobs\RunDailyDummyEntryGeneration;
use App\Jobs\RunDrawGroupGeneration;
use App\Jobs\RunMonthlyDrawExecution;
use Illuminate\Console\Scheduling\Schedule;

/**
 * DOMAIN_LOGIC.md §19, Docs/TASKS.md T-019 — every §19 job with an actual
 * recurring cadence (the Report Export Worker is explicitly "On demand", not
 * scheduled, and its Job class doesn't exist yet — T-018) must be registered
 * on Laravel's scheduler, each with an explicit timezone and
 * `withoutOverlapping()` for retry-safety.
 *
 * Calls `Scheduling::register()` directly against a fresh `new Schedule()`
 * rather than resolving `Schedule::class` through the container:
 * `bootstrap/app.php`'s `withSchedule()` only wires that registration via a
 * `Illuminate\Console\Application::$bootstrappers` static array that fires on
 * `Artisan::starting` — in a single test process that ends up running
 * multiple Artisan invocations (RefreshDatabase's own internal `migrate`
 * call, plus anything a test does itself), the registration closure can fire
 * more than once before `Schedule::class` is first resolved, double-counting
 * events in a way that has nothing to do with how the scheduler actually
 * behaves in production (a fresh process per `schedule:run` cron tick, where
 * this fires exactly once). Testing `Scheduling::register()` directly avoids
 * that entirely and is deterministic.
 */
function registeredSchedule(): Schedule
{
    $schedule = new Schedule;
    Scheduling::register($schedule);

    return $schedule;
}

function scheduledEvent(Schedule $schedule, string $jobClass)
{
    return collect($schedule->events())->first(fn ($event) => $event->getSummaryForDisplay() === $jobClass);
}

test('exactly the 6 recurring §19 jobs are registered, none more', function () {
    expect(registeredSchedule()->events())->toHaveCount(6);
});

test('every scheduled job runs with an explicit Asia/Kolkata timezone and withoutOverlapping', function () {
    $schedule = registeredSchedule();

    $jobs = [
        RunDailyDummyEntryGeneration::class,
        RunDrawGroupGeneration::class,
        RunMonthlyDrawExecution::class,
        ProcessEmiDueStatuses::class,
        EvaluateMonthlyPairMilestones::class,
        ProcessBoosterPayouts::class,
    ];

    foreach ($jobs as $jobClass) {
        $event = scheduledEvent($schedule, $jobClass);

        expect($event)->not->toBeNull();
        expect($event->timezone)->toBe('Asia/Kolkata');
        expect($event->withoutOverlapping)->toBeTrue();
    }
});

test('the Daily Company Direct Generator runs daily', function () {
    $event = scheduledEvent(registeredSchedule(), RunDailyDummyEntryGeneration::class);

    expect($event->getExpression())->toBe('30 0 * * *');
});

test('the Draw Group Generator runs on the 15th at 00:00, before the Monthly Draw Executor at 12:00', function () {
    $schedule = registeredSchedule();

    expect(scheduledEvent($schedule, RunDrawGroupGeneration::class)->getExpression())->toBe('0 0 15 * *');
    expect(scheduledEvent($schedule, RunMonthlyDrawExecution::class)->getExpression())->toBe('0 12 15 * *');
});

test('the EMI Due Processor and Booster Payout Processor run daily', function () {
    $schedule = registeredSchedule();

    expect(scheduledEvent($schedule, ProcessEmiDueStatuses::class)->getExpression())->toBe('5 0 * * *');
    expect(scheduledEvent($schedule, ProcessBoosterPayouts::class)->getExpression())->toBe('45 0 * * *');
});

test('the Pair/Reward Monthly Evaluator runs on the last day of the current month', function () {
    $event = scheduledEvent(registeredSchedule(), EvaluateMonthlyPairMilestones::class);
    $expectedDay = now()->endOfMonth()->day;

    expect($event->getExpression())->toBe("30 23 {$expectedDay} * *");
});

test('the Report Export Worker is deliberately not scheduled — it is dispatched on demand once T-018 builds it', function () {
    $summaries = collect(registeredSchedule()->events())->map->getSummaryForDisplay();

    expect($summaries->contains(fn ($s) => str_contains($s, 'ReportExportWorker')))->toBeFalse();
});
