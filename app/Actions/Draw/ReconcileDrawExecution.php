<?php

namespace App\Actions\Draw;

use App\Models\DrawExecution;
use App\Models\DrawExecutionCorrection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §8.6/§18, T-017 pre-coding pass (`DOMAIN_LOGIC.md` §21) —
 * closes out an executed draw cycle month as `reconciled`. Never touches the
 * immutable result fields (`winner_member_id`/`rng_proof`/
 * `upline_benefit_member_id`) — an optional correction note is instead
 * written to the separate, append-only `draw_execution_corrections` table,
 * the literal "reversal/correction audit record" §8.6 requires.
 */
class ReconcileDrawExecution
{
    public function __invoke(DrawExecution $execution, User $operator, ?string $correctionNote = null): DrawExecution
    {
        if ($execution->status !== 'executed') {
            throw ValidationException::withMessages([
                'status' => 'Only an executed draw can be reconciled.',
            ]);
        }

        return DB::transaction(function () use ($execution, $operator, $correctionNote) {
            $execution->update([
                'status' => 'reconciled',
                'reconciled_at' => now(),
                'reconciled_by' => $operator->id,
            ]);

            if ($correctionNote !== null && $correctionNote !== '') {
                DrawExecutionCorrection::create([
                    'draw_execution_id' => $execution->id,
                    'note' => $correctionNote,
                    'created_by' => $operator->id,
                    'created_at' => now(),
                ]);
            }

            return $execution;
        });
    }
}
