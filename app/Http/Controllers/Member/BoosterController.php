<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\BoosterPayoutSchedule;
use App\Models\BoosterQualification;
use App\Services\BinaryTeamSizeCounter;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M12 — qualification/progress/6-month benefit schedule per level (DOMAIN_LOGIC.md §9). */
class BoosterController extends Controller
{
    public function __construct(
        private readonly RuleVersionService $rules,
        private readonly BinaryTeamSizeCounter $teamSizeCounter,
    ) {}

    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $teamSize = $this->teamSizeCounter->countSides($member);

        $qualifications = $member->boosterQualifications()
            ->with('payoutSchedules')
            ->orderBy('level_no')
            ->get()
            ->map($this->mapQualification(...));

        return Inertia::render('member/booster', [
            'direct_count' => $member->directs()->where('status', 'active')->count(),
            'team_size' => $teamSize,
            'levels' => $this->rules->value('booster_levels', []),
            'qualifications' => $qualifications,
        ]);
    }

    /** @return array<string, mixed> */
    private function mapQualification(BoosterQualification $qualification): array
    {
        return [
            'level_no' => $qualification->level_no,
            'qualified_at' => Dates::date($qualification->qualified_at),
            'schedules' => $qualification->payoutSchedules->map($this->mapSchedule(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function mapSchedule(BoosterPayoutSchedule $schedule): array
    {
        return [
            'month_no' => $schedule->month_no,
            'scheduled_date' => Dates::date($schedule->scheduled_date),
            'amount' => $schedule->amount,
            'status' => $schedule->status,
        ];
    }
}
