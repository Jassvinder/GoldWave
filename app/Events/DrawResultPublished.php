<?php

namespace App\Events;

use App\Models\DrawExecution;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * DOMAIN_LOGIC.md §8.4 point 6 — "Real-Time Draw Result Update": a member/
 * admin who already has the Draw Page open must receive the result
 * automatically. This event is fired by `ExecuteMonthlyDraw` the moment a
 * winner is recorded; the actual Draw Page (T-015/T-017) and its
 * slot-machine animation are out of this task's scope (see DOMAIN_LOGIC.md
 * §21 T-010 pre-coding pass) — this event just makes the real-time push
 * code-complete and ready to work the moment a real broadcast driver
 * (Reverb, recommended in ARCHITECTURE.md) is installed. Until then,
 * `BROADCAST_CONNECTION=log` means it safely logs instead of pushing.
 */
class DrawResultPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DrawExecution $execution) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("draw-group.{$this->execution->draw_group_id}")];
    }

    public function broadcastAs(): string
    {
        return 'draw.result';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'draw_group_id' => $this->execution->draw_group_id,
            'cycle_month_no' => $this->execution->cycle_month_no,
            'winner_member_id' => $this->execution->winner_member_id,
            'upline_benefit_member_id' => $this->execution->upline_benefit_member_id,
            'executed_at' => $this->execution->executed_at ? Carbon::parse($this->execution->executed_at)->toIso8601String() : null,
        ];
    }
}
