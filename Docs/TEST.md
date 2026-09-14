# Verification Guide

## Status

- Status: Active — commands verified against the scaffold; business-rule acceptance scenarios below are written and worked by hand (10-09-2026) but not yet automated as Pest tests (that happens per feature task, see `Docs/TASKS.md` T-020, using these exact fixtures).
- Last verified: 10-09-2026 (commands run successfully against a fresh scaffold; acceptance-scenario numbers hand-verified against `Docs/DOMAIN_LOGIC.md`'s rates)
- Test owner / CI source: [TBD — no CI configured yet]

## Commands

| Purpose                       | Command                      | When to run                                                       | Expected result                                                           |
| ----------------------------- | ---------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Run backend test suite        | `php artisan test`           | Before every commit that touches `app/`, `routes/`, `database/`   | All tests pass (Pest, run via PHPUnit runner)                             |
| Build frontend for production | `npm run build`              | Before every commit that touches `resources/js`, or before deploy | Vite build completes without errors                                       |
| Run frontend dev server       | `npm run dev`                | During frontend development                                       | Vite dev server starts, HMR works                                         |
| Run backend dev server        | `php artisan serve`          | During backend development / manual QA                            | Serves on `http://127.0.0.1:8000`, returns HTTP 200 on `/` and `/login`   |
| Run DB migrations             | `php artisan migrate`        | After pulling new migrations                                      | Migrations apply cleanly against the local `goldwave` PostgreSQL database |
| Type-check frontend           | `npm run types:check`        | Before committing TS/TSX changes                                  | No TypeScript errors                                                      |
| Lint/format check (frontend)  | `npm run check`              | Before committing frontend changes                                | No lint errors (`npm run check:fix` to auto-fix)                          |
| Lint/format (backend)         | `vendor/bin/pint`            | Before committing PHP changes                                     | No style violations (Laravel Pint)                                        |
| Static analysis (backend)     | `vendor/bin/phpstan analyse` | Before committing PHP changes                                     | No new static-analysis errors (Larastan)                                  |

Do not fabricate commands. This table is verified against the actual scaffolded project (Laravel 13 + React starter kit + Pest + Pint + Larastan). Update it if any tooling changes.

## Risk-based Coverage

This project is financial/compensation-heavy; the areas below carry the highest correctness risk per `DOMAIN_LOGIC.md` and must get dedicated automated coverage once the codebase exists — not just manual spot-checks.

| Change area                                                                   | Minimum verification                                                                                                                     | Regression focus                                                                                  |
| ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Level Income calculation (`DOMAIN_LOGIC.md` §6)                               | Unit test the 12-level Sponsor/Direct chain resolution and rate application against known fixtures                                       | Never falls back to Binary Position chain; correct rounding; correct skipped-beneficiary handling |
| Pair/Reward consumption (`DOMAIN_LOGIC.md` §7)                                | Unit test incremental consumption across multiple milestones in one evaluation, plus carry-forward                                       | Consumed entries are never reused; milestone table stays in sync with Super Admin config          |
| Binary placement algorithm (`DOMAIN_LOGIC.md` §4.3)                           | Unit test occupied-side traversal at varying depths, both Left and Right                                                                 | Member always lands on the side they selected, never the opposite side                            |
| Payment webhook idempotency (`DOMAIN_LOGIC.md` §10.2, §19)                    | Integration test duplicate/retried webhook calls                                                                                         | No duplicate joining, income, or ledger entries on retry                                          |
| Draw execution (`DOMAIN_LOGIC.md` §8)                                         | Integration test group generation cutoff, sequential execution, winner removal from future draws, upline benefit threshold (≥10 directs) | Idempotent group generation and execution; no double winners                                      |
| Store Wallet deduction (`DOMAIN_LOGIC.md` §16.1)                              | Integration test concurrent deduction attempts against a low balance                                                                     | Atomic deduction; insufficient balance always blocks; no duplicate deduction on retry             |
| Purchase/Repurchase Income duplicate-beneficiary rule (`DOMAIN_LOGIC.md` §15) | Unit test a Sponsor who also appears deeper in the chain                                                                                 | Sponsor receives only the 1% benefit, never a second smaller payout                               |
| Payout processing (`DOMAIN_LOGIC.md` §11)                                     | Integration test approval → processed and approval → failed paths                                                                        | Failed payout never leaves a permanent debit; wallet hold released correctly                      |
| Scheduled jobs generally (`DOMAIN_LOGIC.md` §19)                              | Re-run each job twice with the same trigger data                                                                                         | No duplicate financial side effects on re-run                                                     |

## Business-Rule Acceptance Scenarios (Given/When/Then, worked examples)

These are the concrete numeric acceptance tests every compensation Action (`Docs/ARCHITECTURE.md`) must satisfy, written **before** any calculation code, so implementation has real fixtures to code against instead of the source's abstract percentages. Each one should become an actual Pest test with these exact numbers once the relevant task starts. Amounts are illustrative fixtures for testing, not business defaults, unless stated otherwise.

### 1. Level Income — full 12-level chain

**Given** a Sponsor/Direct chain A→B→C→D→E→F→G→H→I→J→K→L (A is the paying member's direct Sponsor, L is 12 levels up) and the active rule version's `level_income_rates` = `{1:5, 2:2, 3:2, 4-8:1, 9-12:0.5}` (percent).
**When** the paying member makes a confirmed ₹5,000 payment (one-time or EMI installment — same formula either way).
**Then** exactly 12 `income_ledger_calculations` rows (type=`level_income`) are created:

| Level | Beneficiary   | Rate      | Amount   |
| ----- | ------------- | --------- | -------- |
| 1     | A             | 5%        | ₹250     |
| 2     | B             | 2%        | ₹100     |
| 3     | C             | 2%        | ₹100     |
| 4–8   | D, E, F, G, H | 1% each   | ₹50 each |
| 9–12  | I, J, K, L    | 0.5% each | ₹25 each |

Total distributed: ₹800 (16% of ₹5,000). **Edge case:** if the chain is shorter than 12 (e.g. only 5 sponsors exist above the payer), levels 6–12 get an `eligibility_status = skipped` row with a reason, not a silently missing row (`skip_reason = chain_too_short`, `beneficiary_member_id = null` — resolved 13-09-2026, `DOMAIN_LOGIC.md` §21, since no member exists at that level to attach). **Edge case:** if a resolved beneficiary's own `members.status` is anything other than `active` (e.g. `cancelled`), that level is likewise `skipped` with `skip_reason = upline_inactive`, `beneficiary_member_id` still recorded (unlike the chain-too-short case) — reuses the same Active/not-Active definition already settled for sponsor-inactive-at-registration, not a new rule (resolved 13-09-2026, `DOMAIN_LOGIC.md` §21).

### 2. Pair/Reward — incremental consumption crossing a milestone, with carry-forward

**Given** milestone 1 needs 5L/5R for ₹500, milestone 2 needs 50L/50R for ₹5,000, and a member currently has 3 unused Left and 3 unused Right pair entries (milestone 1 not yet reached).
**When** 50 new eligible Left entries and 50 new eligible Right entries arrive in the same monthly evaluation (unused pool becomes 53L/53R).
**Then** milestone 1 is evaluated first: consume 5L+5R → create one `pair_reward_transactions` row (milestone_no=1, reward=₹500) → 48L/48R remain unused. Milestone 2 is evaluated next: 48 < 50, not reached this cycle → the 48L/48R **carry forward** unconsumed (no reward, no error).
**When** (next month) 5 more Left and 5 more Right arrive (unused pool becomes 53L/53R again).
**Then** milestone 2 now succeeds: consume 50L+50R → create one row (milestone_no=2, reward=₹5,000) → 3L/3R remain, carried forward again. **Invariant to test:** the 5L/5R consumed for milestone 1 are marked `status=consumed, consumed_for_milestone_no=1` and must never be selected again by any later evaluation.

**Beneficiary-chain regression to test (resolved 13-09-2026, `DOMAIN_LOGIC.md` §7's Pair/Reward Beneficiary Chain Rule):** given a Binary Position chain P1(direct placement parent)→P2→P3 above a newly-eligible member N, where N sits on P1's Right leg, P1 is itself on P2's Left leg, and P2 is on P3's Right leg — when N's joining becomes eligible, exactly 3 `pair_entries` rows are created: P1 gets one **Right** entry (N is directly on P1's right), P2 gets one **Left** entry (P1's whole subtree, including N, sits on P2's left), and P3 gets one **Right** entry (P2's whole subtree sits on P3's right) — a team-size count all the way up, not just crediting P1.

### 3. Income Booster — qualify once, pay 6 months regardless of later drop-off (RESOLVED 12-09-2026: duration 3→6 months, concurrency confirmed)

**Given** Booster Level 1 requires 10 directs + 500 binary team size (split 250 Left / 250 Right, `DOMAIN_LOGIC.md` §9) for ₹5,000/month × 6 months, and a member reaches 12 directs and 520 team size (260L/260R) in month 1 (Level 2's 20-directs/1,500-team threshold is not met, so Level 1 is the only level qualifying so far).
**When** the qualification is evaluated.
**Then** one `booster_qualifications` row (level_no=1) is created, and exactly 6 `booster_payout_schedules` rows are created (month_no 1–6, ₹5,000 each, total ₹30,000).
**When** the member's direct count later drops back to 4 in month 2 (before month 2's payout runs).
**Then** month 2's ₹5,000 is still paid — qualification is a one-time gate, not a maintained condition (`DOMAIN_LOGIC.md` §9).
**When**, instead, the same member newly crosses the Level 2 threshold (20 directs, 1,500 team split 750L/750R) in month 2 while the Level 1 schedule (months 1–6) is still running.
**Then** a **second, independent** `booster_qualifications` row (level_no=2) is created, with its own 6 `booster_payout_schedules` rows (month_no 2–7, ₹20,000 each, total ₹1,20,000) running **concurrently** alongside the still-active Level-1 schedule — neither schedule is cancelled or merged (`DOMAIN_LOGIC.md` §9.1 concurrency rule, resolved via direct user confirmation 12-09-2026). **Regression to test:** in the same month where both schedules are active, the member's wallet ledger must show two separate booster credit entries (₹5,000 from Level 1 + ₹20,000 from Level 2 = ₹25,000 that month), not one merged/overwritten entry.
**Given** instead a member has 12 directs and a 490 Left / 10 Right binary team (500 total, the correct combined total for Level 1).
**Then** the member does **not** qualify for Level 1 — the split is enforced independently, not just the combined total (`DOMAIN_LOGIC.md` §21 T-011 pre-coding pass, user-confirmed). No `booster_qualifications` row is created until both legs independently reach at least 250.

### 4. Purchase/Repurchase Upline Income — full chain with the duplicate-beneficiary guard

**Given** member P's Sponsor chain is A(L1, direct sponsor)→B(L2)→C(L3)→D(L4)→E(L5)→F(L6)→G(L7)→H(L8)→I(L9)→J(L10)→K(L11)→L(L12), and rates are self 2%, direct Sponsor 1%, L2–6 0.5% each, L7–12 0.25% each.
**When** P makes a confirmed ₹10,000 store purchase.
**Then**: P receives ₹200 (2%); A receives ₹100 (1%, as direct Sponsor — not the 0.5% a plain "Level 2" rate would imply); B–F receive ₹50 each (0.5%); G–L receive ₹25 each (0.25%). Total distributed: ₹700 (7% of the sale — 2% self + 5% upline). **Regression to test:** because a Sponsor/Direct chain is a strict ancestor path (see the clarification in `DOMAIN_LOGIC.md` §15/§21), no member can structurally appear twice in this list — the duplicate-beneficiary guard should never actually trigger in a correct chain walk; write a test asserting it stays inert (12 distinct beneficiaries, no member repeated) rather than trying to force a duplicate that shouldn't be reachable.

### 5. Store Profit Distribution — owner + 3 levels (RESOLVED 12-09-2026: co-application with scenario 4 confirmed)

**Given** a confirmed store sale with distributable amount ₹50,000, and rates Owner 2%, Sponsor/Direct L1 0.5%, L2 0.25%, L3 0.25%.
**When** the sale is confirmed.
**Then** 4 `store_profit_distributions` rows: Store Owner ₹1,000, L1 ₹250, L2 ₹125, L3 ₹125 — total ₹1,500 (3% of the sale).
**When**, additionally, this same sale is a **member's own jewellery purchase** (scenario 2 of the 3 resolved store-sale scenarios, `DOMAIN_LOGIC.md` §16.4) rather than a walk-in/non-member sale.
**Then** scenario 4's Purchase/Repurchase Upline Income (2% self / 1% direct Sponsor / 0.5% L2–6 / 0.25% L7–12) **also fires on this same transaction**, in addition to the 4 Store Profit Distribution rows above — the two ledgers are independent and both are created; this is no longer blocked on client confirmation (`DOMAIN_LOGIC.md` §21). **Regression to test:** a walk-in/non-member sale (no purchasing member) creates only the 4 `store_profit_distributions` rows and zero `income_ledger_calculations` rows — assert both shapes, not just the combined case.

### 6. Monthly Draw — grouping, execution, upline benefit threshold

**Given** a configured group size of 200 and exactly 250 members who are currently Draw-eligible (active, not an unassigned dummy, and meeting `DOMAIN_LOGIC.md` §8.7's completed-EMI threshold for their plan) and not yet in any draw group, ordered by `members.id`.
**When** grouping runs.
**Then** exactly **one** `draw_groups` row is created (`size=200`) with the 200 lowest-id eligible members as its frozen `draw_group_members` roster; the remaining 50 stay ungrouped. **When** grouping runs again immediately after with no new eligible members added, **then** nothing changes — no second group, no duplicate roster rows (idempotent). **When**, later, 150 more members become newly eligible (50 leftover + 150 new = 200), **then** exactly one more group forms from precisely those 200 — the earlier 50 are not re-ordered or split across groups.
**Given** a member on Plan A (₹1,000×20, requires 6 completed EMIs for Draw eligibility per §8.7) who has only 3 completed installments, and a separate unassigned company-dummy member.
**Then** neither is included in any group's roster — the EMI member because the threshold isn't met yet (they become eligible once installment 6 confirms, joining a _future_ grouping batch, never retroactively added to an already-formed group), the dummy because §14.2 point 5 excludes unassigned dummies from all compensation.

**Given** a group's month 1 execution has already run (a winner recorded, that member's `draw_group_members.is_winner_removed=true`) and that group/month's `draw_group_month_configs` row exists.
**When** month 2's execution runs for the same group.
**Then** a new `draw_executions` row is created for `cycle_month_no=2`, selecting from the 199 remaining (not-yet-removed) members — the month-1 winner can never be selected again for this group. **When** month 2's execution is run a second time by mistake (e.g. a retried scheduled job), **then** nothing changes — the existing `(draw_group_id, cycle_month_no)` row is left alone, no second winner is created for the same month (idempotent, §8.6).
**Given** instead the group/month's `draw_group_month_configs` row has not been configured yet (Super Admin hasn't set that month's Prize Name/Value).
**Then** execution is skipped for that group this run — no `draw_executions` row is created, no prize value is invented, and other groups whose month _is_ configured still execute normally in the same run.
**Given** a group on its 20th and final cycle month.
**When** that month's execution completes.
**Then** the `draw_groups.status` becomes `completed`, and no further executions are ever attempted for that group.

**Given** a draw group executes and selects winner W, whose direct Sponsor S currently has 12 Direct Members.
**When** the upline benefit is evaluated.
**Then** the same `draw_executions` row records `upline_benefit_member_id = S.id` — S receives the same prize item/value as W (threshold is "at least 10", so exactly 10 also qualifies — test the boundary at exactly 10, not just above it).
**Given** instead S has 9 Direct Members.
**Then** `upline_benefit_member_id` stays null on that row — no upline benefit — and W still receives their own prize; the draw does not fail or block on a missing upline beneficiary.

### 7. Payout — request, hold, processing, TDS deduction, reject/cancel, bulk (illustrative rates; real defaults are min ₹500 / TDS 0% / fee 0% until Super Admin configures otherwise)

**Given** a member has a wallet balance of ₹12,000 and no existing hold, and the configured minimum payout request is ₹500.
**When** the member submits a payout request for ₹10,000 against a verified bank detail.
**Then** a `payout_requests` row is created with `status=pending`, `requested_amount=10000`; a `wallet_ledger_entries` hold row is created (`entry_type=debit`, `status=pending`, `amount=10000`) and linked via `hold_ledger_entry_id`; `wallet_balance` stays ₹12,000 but `wallet_hold_amount` becomes ₹10,000, so `availableBalance()` is now ₹2,000.
**Given** instead the member requests ₹100 (below the ₹500 minimum).
**Then** the request is rejected at validation — no `payout_requests` row, no hold — with a clear minimum-amount error.
**Given** instead the member's available balance (`wallet_balance − wallet_hold_amount`) is only ₹8,000.
**Then** a ₹10,000 request is rejected at validation for insufficient balance — no row, no hold.

**Given** the ₹10,000 pending request above and, purely for this test, a configured TDS rate of 5% and processing fee of 1% (not real business defaults — see `DOMAIN_LOGIC.md` §11, current defaults are both 0%).
**When** Super Admin processes the payout via Bank Transfer with reference `UTR12345`.
**Then** a `payout_transactions` row is created with `amount_snapshot=10000`, `tds_amount=500`, `processing_fee=100`, `method=bank_transfer`, `reference=UTR12345`, `status=processed`; the beneficiary net transfer is ₹9,400 (10000 − 500 − 100); the hold is confirmed (`wallet_balance` becomes ₹2,000, `wallet_hold_amount` returns to ₹0); the `payout_requests` row becomes `status=processed`. **Also test:** at the real defaults (TDS=0%, fee=0%), `tds_amount=0`, `processing_fee=0`, and the net transfer equals the full requested amount exactly (no off-by-rounding).

**Given** a different ₹3,000 pending request (hold already placed, balance otherwise ₹5,000 available).
**When** Super Admin rejects the request.
**Then** `payout_requests.status` becomes `rejected`, the hold is released (`wallet_hold_amount` decreases by ₹3,000, `wallet_balance` unchanged), and **no** `payout_transactions` row is created. **Also test:** the identical hold-release/no-transaction-row shape when Super Admin cancels a pending request instead (`status=cancelled`) — reject and cancel differ only in the resulting status, never in the wallet effect.
**Given** a request that has already reached `status=processed`.
**When** Reject or Cancel is attempted against it.
**Then** it is refused — a terminal request can never be rejected/cancelled/reprocessed (§11.2 point 10, confirmed payouts are final).

**Given** 3 separate members each have one pending, approved-for-payment request (₹2,000, ₹3,500, ₹1,200).
**When** Super Admin processes all 3 together as one bulk/batch Bank Transfer submission.
**Then** 3 separate `payout_transactions` rows are created, each with its own `amount_snapshot`/`reference`/`status`/audit trail, but all sharing the same `batch_reference`; each member's own hold is confirmed independently. **Regression to test:** if one request's underlying hold was already released (e.g. concurrently rejected) before the batch runs, that one request is skipped/fails without rolling back or blocking the other 2 in the same batch.

**Given** a pending request whose payment attempt is later reported as failed (e.g. a bounced bank transfer, reference `UTR99999`).
**When** Super Admin marks it failed.
**Then** a `payout_transactions` row is still created for the audit trail (`method=bank_transfer`, `reference=UTR99999`, `status=failed`), but the hold is **released, not confirmed** — `wallet_balance` is unchanged and `wallet_hold_amount` drops back to ₹0, so the member's balance stays consistent and no permanent debit is created (§11.2 point 7); `payout_requests.status` becomes `failed`.

### 8. EMI Pair-Qualification threshold — one eligible joining, not one per installment

**Given** a member on the ₹1,000×20 plan (requires a minimum of 6 completed EMIs before Pair/Reward eligibility, `DOMAIN_LOGIC.md` §7.3).
**When** EMI installments 1 through 5 are each confirmed.
**Then** each triggers its own Level Income calculation (scenario 1 applies to every installment independently) but creates **zero** `pair_entries` rows — the member is not yet a "full eligible joining" for pairing purposes.
**When** EMI installment 6 is confirmed.
**Then** exactly **one** `pair_entries` row is created (on whichever side — Left or Right — the member is placed), not six. **Regression to test:** installments 7–20 continue generating Level Income only, never additional pair entries for the same joining.
**Given** instead a member on Plan D (₹10,000×10, requires a minimum of 1 completed EMI before Pair/Reward eligibility — RESOLVED 12-09-2026, `DOMAIN_LOGIC.md` §7.3).
**Then** the very first confirmed EMI installment already creates exactly **one** `pair_entries` row — unlike Plan A, there is no multi-installment wait; do not hard-code a "requires more than 1" assumption into the eligibility check.

### 9. Binary Placement — occupied-side traversal, and Sponsor/Placement divergence

**Given** Sponsor S's Left position is occupied by L1, whose own Left is occupied by L2, whose Left is empty; S's Right is empty.
**When** a new member N registers under S's invite code and selects **Left**.
**Then** N is placed at L2's Left (`placement_parent_id = L2.id`, `placement_side = left`), because the traversal walks Left→Left→Left until the first empty slot — **never** falls over to the empty Right slot at S just because it's closer.
**Given** S has personally sponsored 3 different people (X, Y, Z), all of whom individually chose "Left" at their own registration.
**Then** all 3 have `sponsor_id = S.id` (Sponsor/Direct is unaffected by depth), but their `placement_parent_id` values are 3 different nodes progressively deeper down the Left subtree — demonstrating Sponsor/Direct and Binary Position diverging exactly as `DOMAIN_LOGIC.md` §0/§4 requires.
**Given** instead Sponsor S's own member account is currently **not Active** — for any reason, e.g. S's own EMI payment is pending, or Super Admin suspended S (RESOLVED 12-09-2026, `DOMAIN_LOGIC.md` §2.1).
**When** a new member attempts to register using S's invite/sponsor code.
**Then** the code is rejected as invalid — registration is blocked at the same validation step as a genuinely wrong/non-existent code, with the same error presentation; no placement or Customer ID is created. **Regression to test:** once S's own account becomes Active again, the identical code must succeed for a subsequent registration attempt — the check re-evaluates S's live status each time, it is not a one-time/cached flag.

### 10. EMI Rate Booking — Current Rate Booking formula and Future Rate Booking commitment (NEW 12-09-2026 — client spec v2.0)

**Given** a member registers on Plan A (₹1,000/month × 20 months, 100gm Silver) and selects **Current Rate Booking** (`DOMAIN_LOGIC.md` §3.0), and the current silver rate is ₹3,500/tola (100gm = 10 tola).
**When** the EMI schedule is generated.
**Then** Total Current-Rate Jewellery Value = 10 tola × ₹3,500 = ₹35,000; Maintenance Cost = 1% of ₹35,000 = ₹350; EMI Amount = (₹35,000 ÷ 20) + ₹350 = ₹1,750 + ₹350 = **₹2,100/month**, and all 20 `emi_installments` rows are created at ₹2,100 each (total ₹42,000 over the schedule) — not the plan's headline ₹1,000/month, which only applies to Future Rate Booking. The rate used (₹3,500/tola) and the fixed weight (100gm) must be snapshotted on the `emi_schedules` row so this calculation is reproducible even if the silver rate changes next month.
**Given** instead the same member selects **Future Rate Booking**.
**Then** the EMI Amount is simply the plan's base amount, ₹1,000/month for 20 months (no maintenance cost added); Future Jewellery Commitment = ₹1,000 × 20 = ₹20,000; at final EMI completion/delivery, the member receives Silver Jewellery worth ₹20,000 **at whichever silver rate is applicable on the delivery date** — if the rate has moved since booking, the delivered weight is not fixed at 100gm the way Current Rate Booking's is. **Regression to test:** switching the example to Plan D (₹10,000/month × 10 months, 10gm Gold) must use the same two formulas — do not special-case Plan A's numbers into the calculation code. **Note:** the Plan D Pair/Reward completed-EMI count is unrelated to this rate-booking calculation — it defaults to **1** completed EMI (RESOLVED 12-09-2026, `DOMAIN_LOGIC.md` §7.3/§21) — do not conflate the two when writing Plan D fixtures.

### 11. Login & Authentication — pre-activation block, dual login methods, OTP-gated password reset (NEW 12-09-2026 — user-confirmed, no source spec existed for this)

**Given** a member has just submitted registration with a Cash payment mode and is awaiting Super Admin confirmation (`DOMAIN_LOGIC.md` §3.1 step 8 — not yet activated).
**When** they attempt to log in with their registered mobile number, via either OTP or Customer-ID+password.
**Then** login is refused for both methods — login is only possible after activation (§2.2), and this member has no Customer ID yet at all.
**Given** the same member's Cash payment is now confirmed and Super Admin activates them, generating Customer ID `GWL045`.
**When** the member requests an OTP using their registered **mobile number**.
**Then** an OTP is sent via SMS; entering the correct OTP logs them in. **Regression to test:** requesting the OTP via their registered **email** instead must also work — both identifiers are valid, not just mobile.
**When**, instead, the member attempts to log in with User ID `GWL045` and password `GWL045` (i.e. the untouched initial default — RESOLVED 12-09-2026, password defaults to the Customer ID itself).
**Then** login succeeds — this is the expected, deliberate initial state, not a bug to "fix" by rejecting it.
**When** the member later wants to change that password.
**Then** the only path is: authenticate via OTP first (mobile or email), then set the new password from within that OTP-authenticated session — there is no separate "forgot password" flow that skips OTP verification. **Regression to test:** attempting a password-reset request without a valid OTP session must be rejected, even if the member supplies their current (default) password correctly elsewhere.

### 12. EMI installment schedule generation — due-date cadence and overdue transition (NEW 13-09-2026 — user-confirmed, no source spec existed for this)

**Given** a member activates a Plan A membership (20 installments) on **17-02-2026**, Current Rate Booking, EMI amount ₹2,100/month (scenario 10's worked numbers).
**When** the EMI schedule is generated.
**Then** exactly 20 `emi_installments` rows are created with `due_date` on the activation-date anniversary each month: installment 1 = 17-02-2026, installment 2 = 17-03-2026, installment 3 = 17-04-2026, … installment 20 = 17-09-2027 — **not** the 1st of the month or any other fixed calendar day. All 20 rows start at `status = upcoming` (installment 1 may start `due` if generation happens on/after its own due date — see below).
**Given** installment 3 (due 17-04-2026) is unpaid.
**When** the EMI Due Processor runs on 17-04-2026 (the due date itself).
**Then** installment 3's status becomes `due` (not yet `overdue`).
**When** the processor runs again on 18-04-2026 (the very next calendar day), still unpaid.
**Then** installment 3's status becomes `overdue` immediately — **no grace period** — while installment 4 (due 17-05-2026) remains `upcoming`, unaffected by installment 3's lateness. **Regression to test:** paying installment 3 late (after it reached `overdue`) marks only that row `paid` and does **not** shift installment 4's due date (17-05-2026 stays fixed — the schedule never drifts based on actual payment timing, only the original activation-date anniversary).

### 13. Profile Pending Fields (one-time) + Change Request workflow (NEW 13-09-2026 — pre-coding pass, DOMAIN_LOGIC.md §21 T-012)

**Given** a newly-activated member has never submitted their Pending Fields.
**When** they submit PAN `ABCDE1234F`, Aadhaar `123456789012`, a profile photo, address, and bank details (holder name, account number, IFSC, bank name, a passbook/cheque proof image) all together in one submission.
**Then** `members.pan_card`/`aadhaar_card`/`profile_photo_path`/`address` are set, `members.pending_fields_submitted_at` is set to the submission time, and exactly one `member_bank_details` row is created for the member with `verified_at = null` (not yet Super-Admin-verified — payout cannot be requested against it yet, DOMAIN_LOGIC.md §11.2 point 8).
**When** the same member attempts to submit the Pending Fields form again with different values.
**Then** the submission is rejected — fields are locked after the first successful submission, no columns change.
**Given** a PAN of `INVALID123` (wrong format) or an Aadhaar of `12345` (wrong length).
**Then** the submission is rejected at validation before anything is saved — no partial submission (e.g. photo/address saved but PAN rejected).

**Given** the member's `member_bank_details` row above, still unverified.
**When** Super Admin verifies it.
**Then** `verified_by`/`verified_at` are set — the member can now successfully submit a payout request against it (DOMAIN_LOGIC.md §11.1, Docs/TEST.md scenario 7).

**Given** the member's PAN is already locked (submitted above).
**When** the member submits a Change Request for `pan_card` with reason "typo in original submission" and new value `ZYXWV9876G`.
**Then** a `profile_change_requests` row is created: `field_name=pan_card`, `old_value=ABCDE1234F` (the live value snapshotted at request time), `new_value=ZYXWV9876G`, `status=pending`. `members.pan_card` is **unchanged** until Super Admin acts.
**When** the member attempts to submit a second Change Request for `pan_card` while the first is still `pending`.
**Then** it is rejected — one in-flight request per field at a time.
**When** Super Admin approves the pending request.
**Then** `members.pan_card` becomes `ZYXWV9876G`, the request row becomes `status=approved` with `reviewed_by`/`reviewed_at` set, and a database notification is created for the member. **Regression to test:** rejecting a different pending request instead leaves the underlying field completely unchanged, sets `status=rejected` with a `rejection_reason`, and still notifies the member.

**Given** the member's already-verified `member_bank_details` row (from above).
**When** the member submits and Super Admin approves a Change Request for `bank_details` (new account number).
**Then** the existing `member_bank_details` row's fields are updated in place (not a second row created) **and its `verified_at` is reset to null** — a changed beneficiary account must be re-verified by Super Admin before it can be used for a new payout request, even though it was verified before the change.

### 14. Daily Dummy Entries + Leader Assignment (NEW 13-09-2026 — pre-coding pass, DOMAIN_LOGIC.md §21 T-013)

**Given** the dummy-entry setting is disabled (`dummy_entry_enabled = false`).
**When** the daily generation job runs.
**Then** zero `members` rows are created.
**Given** instead it is enabled with `dummy_entry_daily_count = 3`.
**When** the job runs.
**Then** exactly 3 new `members` rows are created, each `is_company_dummy=true`, `dummy_status=unassigned`, `sponsor_id` = the seeded company root Member's id, placed one after another down the root's Right leg (occupied-side traversal — the 2nd and 3rd entries land deeper down the same Right chain, never spilling to Left), each with a real sequential `GWL0N` Customer ID and a non-null `placeholder_name`.

**Given** a real member M registers using a valid sponsor's invite code, and M's Binary Position placement happens to fall under one of the 3 unassigned dummy entries above (a dummy occupies a real Binary Position slot, so a real registrant can land underneath one).
**When** M's registration payment is confirmed.
**Then** Level Income still credits M's real Sponsor/Direct chain normally (dummies are never anyone's Sponsor, so this is unaffected). **But** Pair/Reward's team-size fan-out and Income Booster's qualification walk both correctly **skip** the unassigned dummy ancestor — no `pair_entries` row and no `BoosterQualification` row is ever created _for_ the dummy, even though it structurally still counts toward a _different, real_ ancestor further up the chain's own team-size total. **Regression to test:** this must hold for the root Member too (also `is_company_dummy=true`, `dummy_status` permanently null) if a real member's chain ever reaches that far up.

**Given** one of the 3 unassigned dummy entries above.
**When** Super Admin assigns a real leader's details (name, email, mobile) to it.
**Then** the same `members` row (same id, same Customer ID, same tree position) gains a new `users` row (password seeded to the Customer ID, matching the normal registration precedent), `status` becomes `active`, `dummy_status` becomes `assigned`, and `dummy_assigned_at`/`dummy_assigned_by` are recorded. **Regression to test:** this leader is now a valid beneficiary for Pair/Reward and Booster going forward — a _later_ real registration under their subtree correctly credits them, unlike before assignment.
**Given** daily generation runs again after the assignment above, still enabled.
**Then** the new dummy entries continue down the Right side of the root exactly as before — since the assigned leader is now the bottommost node in that chain, the next occupied-side-traversal call naturally places new entries beneath them, with no special-cased "most recently assigned leader" tracking required.

### 15. Store Inventory — item-wise stock decrement on sale, blocked on insufficient stock (NEW 13-09-2026 — pre-coding pass, DOMAIN_LOGIC.md §21 T-014)

**Given** Store S has one `store_inventory_items` row: "22K Gold Ring", metal=gold, weight=5.000g/unit, quantity=10, price=₹35,000/unit.
**When** a confirmed `new_sale` for this item, quantity=3, is recorded (`ConfirmStoreSale`).
**Then** the item's `quantity` becomes 7, and the `store_sales` row records `store_inventory_item_id`=this item, `quantity`=3 — the deduction and the sale confirmation commit in the same DB transaction.
**Given** the item's quantity is now 7.
**When** a sale for quantity=10 of the same item is attempted.
**Then** the sale is **blocked at confirmation** — no `store_sales` row is created, the item's `quantity` stays at 7 (`DOMAIN_LOGIC.md` §16.8, the same insufficient-balance-blocks-the-transaction pattern already used for Store Wallet deductions, §16.1). **Regression to test:** two truly concurrent sale attempts against a stock of 5, each requesting 3, must not both succeed — only one may confirm. Covered by a real, separate-process concurrency test, not a sequential double-call — see "Concurrency Verification" below (T-020 corrected this note, which previously referenced a Store Wallet concurrent-deduction test that did not actually exist yet).

### 16. Item Buyback — current-market-rate percentage, no compensation triggered (NEW 13-09-2026 — pre-coding pass, DOMAIN_LOGIC.md §21 T-014)

**Given** `item_buyback_percent`=60 and today's `metal_rates` gold rate=₹6,000/gram, and member M brings a 10-gram gold item back to Store S that M originally bought at some earlier, different rate.
**When** Store S records the Buyback (`RecordItemBuyback`).
**Then** the buyback price is calculated on **today's** rate, not the original sale price: 10g × ₹6,000 = ₹60,000 metal value × 60% = **₹36,000** paid to M (outside the app, §17.4 — only the transaction is recorded). A `store_buybacks` row is created snapshotting `rate_per_gram_at_buyback`=6000, `buyback_percent`=60, `price_paid`=36000, `rule_version_id`; a `store_inventory_items` row is created/incremented for Store S with weight=10.000g, quantity+=1, recording the item back into stock (`DOMAIN_LOGIC.md` §16.7).
**Regression to test:** this Buyback creates **zero** `store_profit_distributions` rows and **zero** `income_ledger_calculations` rows — a Buyback is the reverse of a sale and never triggers Store Profit Distribution or Purchase/Repurchase Upline Income (`DOMAIN_LOGIC.md` §16.9).

### 17. Plan Jewellery Delivery via a Store — Store Profit Distribution fires independently of registration-time compensation (NEW 13-09-2026 — pre-coding pass, DOMAIN_LOGIC.md §21 T-014)

**Given** member M registered on Plan C (5gm Gold) with `product_benefits` already recorded at registration (entry_date, rate_per_gram_at_entry), and today's gold rate is ₹6,000/gram, and Store Profit Distribution rates are Owner 2%, L1 0.5%, L2 0.25%, L3 0.25%.
**When** Store S's Admin marks this `product_benefits` row delivered through Store S (`RecordPlanJewelleryDelivery`), with the sale amount taken as 5g × ₹6,000 = ₹30,000.
**Then** a `store_sales` row (`transaction_type`=`new_sale`, `member_id`=M) is created for Store S, and exactly 4 `store_profit_distributions` rows fire on it: Store Owner ₹600, L1 ₹150, L2 ₹75, L3 ₹75 (`DOMAIN_LOGIC.md` §16.4/§16.10). **Regression to test:** this is independent of, and does not duplicate, the Level Income already paid when M's registration payment was originally confirmed (`DOMAIN_LOGIC.md` §6) — the two ledgers (`income_ledger_calculations` type=`level_income` from registration, `store_profit_distributions` from this delivery) are created at different times off different trigger events and must both exist without either being skipped.
**Given** instead M's plan jewellery is never marked delivered through any store (e.g. a Future Rate Booking not yet due for delivery).
**Then** no `store_sales`/`store_profit_distributions` rows are ever created for that `product_benefits` row — matching §16.4 scenario 3's conditional "if... at a given store" wording.

### 18. Report Export — queued lifecycle, row-count/file correctness, and role scope (NEW 14-09-2026 — pre-coding pass, DOMAIN_LOGIC.md §21 T-018)

**Given** a Super Admin requests a CSV export of the Payment In report filtered to `date_from`/`date_to` covering exactly 3 of the company's real `payments` rows (out of a larger total).
**When** `RequestReportExport` is called, then `ProcessReportExport` is run (synchronously in tests via `Queue::fake()`'s `assertPushed` + directly invoking the job, matching this project's existing job-testing convention from `ScheduledJobsTest.php`).
**Then** the `report_exports` row transitions `pending` → `processing` → `ready`, `row_count`=3 (matching the filtered count, not the company-wide total), and the generated CSV file — read back and parsed — contains exactly 3 data rows plus a header row, each matching the 3 real `payments` rows' actual `mode`/`reference`/`amount`/`status`/date values, correctly escaped (a report with a comma inside a text field, e.g. a store name "A, B & Co.", must round-trip through CSV parsing as one field, not split into two).
**Regression to test:** a report request for a report type/filter combination matching **zero** rows still completes as `ready` with `row_count`=0 and a header-only file — never left `pending`/`failed` just because there's nothing to export.
**Given** an exception occurs mid-generation (e.g. a malformed filter reaching the query layer).
**Then** the `report_exports` row is marked `failed` with `error_message` populated, never left silently `processing` forever, and no partial/corrupt file is left downloadable.
**Given** a `role=admin` (Store Owner) or `role=member` user, authenticated.
**When** they request any `super-admin/reports/*` route, including attempting to `download` an existing, `ready` `report_exports` row by guessing/incrementing its numeric ID.
**Then** every request is rejected (403) — Reports Center access is Super-Admin-only per `DOMAIN_LOGIC.md` §2's "no company-wide... access" rule for Admin, and this is a separate module from Member's own M17 / Admin's own A06 reports, which stay untouched and independently scoped.

## Concurrency Verification (T-020, 15-09-2026)

A separate PHPUnit testsuite, `tests/Concurrency/`, verifies genuine parallel-access safety for the financial critical sections `Docs/TEST.md`'s Risk-based Coverage table calls out by name: Store Wallet deduction (§16.1), Store Inventory decrement (§16.8), and the Booster Payout Processor (§19). It is **not** part of the default `php artisan test` run and never runs against SQLite — see `DOMAIN_LOGIC.md` §21's T-020 pre-coding entry for why (SQLite `:memory:` can't even be shared across two connections, so a real race can't be produced against it; the code's actual `lockForUpdate()` behavior is Postgres-specific). Run it explicitly, against the real dev Postgres database, with:

```
php artisan test --configuration=phpunit.concurrency.xml
```

(a separate configuration file, `phpunit.concurrency.xml`, not a testsuite inside the main `phpunit.xml` — PHPUnit's `<php>` environment block is global to one config file, so the only way to point just this suite at the real Postgres connection without forcing every other suite onto it too is a dedicated file.)

Each test launches several real, separate OS processes (via `Illuminate\Support\Facades\Process::start()`) that race to write the same row before any of them commits, then asserts the database's final state is exactly what the starting balance/stock could support — never an overdraft, never a lost update — using throwaway fixtures created and cleaned up around the test, the same discipline this project already uses for every other manual verification against the real dev database. Payment-webhook double-delivery was considered and deliberately left out of this pass (its idempotency is already enforced by a DB unique constraint, not a `lockForUpdate()` critical section, so it fails safely under a real race regardless) — see `DOMAIN_LOGIC.md` §21's T-020 entry, point 3, for the full reasoning; a small, separable follow-on if ever needed later.

Built and verified non-flaky across 3 repeated runs: `tests/Concurrency/StoreConcurrencyTest.php` (Store Wallet deduction), `tests/Concurrency/StoreInventoryConcurrencyTest.php` (Store Inventory decrement), `tests/Concurrency/BoosterPayoutConcurrencyTest.php` (Booster Payout Processor).

## Manual QA

[TBD / Not applicable until the UI exists] — once pages are built, manual QA should walk the end-to-end workflows in `FLOWCHART.md` (registration, EMI payment, store purchase/repurchase, payout request) in addition to automated coverage.

## Change Rules

Document commands that actually work in this repository, test data/setup assumptions, and known gaps. Never claim coverage that was not run.
