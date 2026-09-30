# Earnings Testing Guide

## Status

- Status: Active
- Last verified: 22-09-2026
- Owner / evidence: written for the user to test compensation correctness on the running app, without needing to ask what to click. Companion to `TEST.md` (which holds the worked-example numbers this guide points at) and `DOMAIN_LOGIC.md` (which owns the actual rules).

This file is a **practical playbook** — the exact clicks/commands to test that Level Income, Pair/Reward, Income Booster, Purchase/Repurchase Income, Store Profit Distribution and the Monthly Draw are calculating correctly. It does not restate the business rules (see `DOMAIN_LOGIC.md`) or the worked numbers (see `TEST.md`'s "Business-Rule Acceptance Scenarios").

---

## 1. The fast way — Earnings Verification (recommended first step)

The single easiest check. It re-calculates every earning independently from the original payments/sales/tree and compares it with what was actually credited — it does **not** trust the same code that produced the numbers in the first place.

**In the browser:**

1. Log in as Super Admin: `superadmin@goldwave.test` / `password`.
2. Go to **Compensation → Earnings Verification** in the sidebar.
3. Press **Run verification**.
4. A green "All earnings match their source events" banner means everything is consistent. A red banner lists exactly what differs — which payment/sale/member, what was expected, what was stored.

**From the command line** (same engine, useful after any change or before a demo):

```
php artisan earnings:verify
```

Exits `0` when clean, `1` when a difference is found (so it can also gate a deploy). Add `--limit=500` to see more findings per check, or `--only=level,pair` to run just some checks (valid keys: `level`, `purchase`, `store`, `pair`, `booster`, `ledger`, `wallet`).

**What it checks:** Level Income, Purchase/Repurchase Income, Store Profit Distribution, Pair entries and Pair/Reward, Income Booster payout schedules, that every credited earning has exactly one correct wallet-ledger entry, and that every member's cached wallet balance equals their ledger.

**What it does _not_ check** (re-derive these by hand, see §3 below): whether a member _qualifies_ for a given Booster level in the first place, and Monthly Draw eligibility/winner selection — it only checks that the payouts/records that _were_ created are internally correct.

**A "warning" is not a bug.** It means something that can legitimately differ over time — e.g. an upline member's account status changed after their income was already calculated. Only red "errors" mean something is actually wrong.

---

## 2. Where an earning actually shows up

| Earning                    | Where the member sees it                                                                       | Where the Super Admin sees it                                                                               |
| -------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Level Income               | Member → **Level Income** page (per-level history) and **Wallet** ledger (`level_income` rows) | Member Detail → Income History; **Compensation Audit** page (every calculation, incl. skipped ones and why) |
| Pair/Reward                | Member → **Pair/Reward** page — one milestone table with Reward + Date filled in once achieved | Member Detail → Income History; Compensation Audit                                                          |
| Income Booster             | Member → **Income Booster** page (qualification, monthly schedule, paid/pending)               | Member Detail → Booster history                                                                             |
| Purchase/Repurchase Income | Member → **Wallet** ledger (`purchase_repurchase_income` rows)                                 | Member Detail → Income History; Compensation Audit                                                          |
| Store Profit Distribution  | Member → **Wallet** ledger (`store_distribution` rows)                                         | Member Detail → "Store Profit Distributions"; Store Wallet Management                                       |
| Monthly Draw prize         | Member → **Monthly Draw** page (own status, winner history)                                    | Draw Management page                                                                                        |
| Payout (withdrawal)        | Member → **Payout Request** / **Payout History**                                               | Payout Requests queue                                                                                       |

**A Draw prize is jewellery, not a wallet credit** — a winner's own prize and a qualifying Sponsor's "upline benefit" are both recorded on the draw execution, never added to `wallet_balance`. Do not expect to see a Draw amount in the Wallet ledger.

Every wallet credit row also carries a plain-English description (e.g. "Level 3 income from GWL045's payment #812") — that description alone is usually enough to sanity-check _why_ an entry exists without opening the Compensation Audit page.

---

## 3. When each earning is actually calculated

| Earning                            | Trigger                                                    | Timing                                                                                |
| ---------------------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Level Income                       | A payment (registration or an EMI instalment) is confirmed | **Immediately** — online payment verified, or Super Admin approves a cash payment     |
| Purchase/Repurchase Income         | A store sale is confirmed                                  | **Immediately**                                                                       |
| Store Profit Distribution          | A store sale is confirmed                                  | **Immediately**                                                                       |
| Pair entries (Left/Right business) | A member's qualifying joining/EMI payment is confirmed     | **Immediately**                                                                       |
| Pair/Reward payout                 | Milestone thresholds are met                               | **Month-end, 23:30 Asia/Kolkata** (the "Pair/Reward Monthly Evaluator" scheduled job) |
| Income Booster qualification       | A registration payment is confirmed                        | **Immediately** (creates the payout schedule: 12 months for Level 1, 6 for Levels 2–3)|
| Income Booster monthly payout      | Each scheduled month's date arrives                        | **Daily, 00:45 Asia/Kolkata** ("Booster Payout Processor")                            |
| Monthly Draw grouping              | The 15th of the month                                      | **00:00 Asia/Kolkata**                                                                |
| Monthly Draw execution             | The 15th of the month                                      | **12:00 Asia/Kolkata**, after grouping                                                |
| EMI due-status transitions         | Daily                                                      | **00:05 Asia/Kolkata**                                                                |
| EMI due reminders                  | Daily                                                      | **09:00 Asia/Kolkata**                                                                |

**On the local dev machine, none of the daily/monthly scheduled jobs run by themselves** — nothing is watching the clock unless you start it. To test something that is normally month-end or daily without waiting:

- Run one job immediately: `php artisan tinker --execute="(new App\Jobs\EvaluateMonthlyPairMilestones)->handle(app(App\Actions\Compensation\EvaluatePairMilestones::class));"` (Pair/Reward), or swap in `App\Jobs\ProcessBoosterPayouts`, `App\Jobs\ProcessEmiDueStatuses`, `App\Jobs\SendEmiReminders` the same way.
- Or run the whole scheduler once: `php artisan schedule:run` (only fires jobs actually due _today_).
- In production, both a cron entry (`* * * * * php artisan schedule:run`) **and** a running queue worker (`php artisan queue:work`) are required — see `DEPLOYMENT.md`.

---

## 4. A small, hand-checkable walkthrough (do this once to build trust in the numbers)

Registering 4-5 members by hand and checking the numbers on paper is more reliable than eyeballing the 500-member demo data, where nothing is traceable by eye.

1. **Reset to a clean, small database:** `php artisan migrate:fresh --seed` (do **not** run the demo network seeder this time).
2. **Note the seeded rates** you'll need for arithmetic: Super Admin → **Rule Versions** page shows Level Income %, Pair Value per entry, Store Profit %, Purchase/Repurchase %. `TEST.md` scenario 1 has the exact default numbers already worked out (e.g. a ₹5,000 payment → L1 ₹250, L2–3 ₹100 each, L4–8 ₹50 each, L9–12 ₹25 each, total ₹800).
3. **Register a short chain by hand** at `/join`: a member A sponsored by the seeded company root (Customer ID `GWL-ROOT`, set in `CompanyRootMemberSeeder`; the first real registration gets `GWL01`), then B sponsored by A, then C sponsored by B. Use **Cash** payment mode so you control exactly when it activates.
4. **Approve each cash payment** as Super Admin (Cash Payments queue). Approving is what actually triggers Level Income — confirm this on the clock, not just on submission.
5. **Check Compensation Audit** (Super Admin) immediately after each approval: filter by the paying member's Customer ID, confirm one row per level 1–12, the right beneficiary, the right rate, and `chain_too_short` skips once the chain runs out (a 3-member chain has real beneficiaries only at levels 1–2).
6. **Check the beneficiary's Wallet ledger** shows the matching credit, and that **Wallet balance** increased by exactly that amount.
7. **Cross-check with a store sale:** create a store (Super Admin → Store Management), record a New Sale against member C. Confirm Purchase/Repurchase Income (self + upline chain) and Store Profit Distribution (owner + up to 3 sponsor levels) both appear, with amounts matching the sale's `sale_amount` × the configured %.
8. **Finish with the fast check:** run `php artisan earnings:verify` (§1) — it should report 0 errors against everything you just did by hand.

This walkthrough plus §1's automated re-computation together cover both "does the arithmetic match the stored rule %" and "does the stored data match what a human expects from the raw events."

---

## 5. Testing Pair/Reward, Booster and Draw specifically

These three are harder to eyeball because they depend on team size, elapsed time, or a random draw — use these shortcuts rather than trying to grow a real 200+ member team by hand.

- **Pair/Reward:** the demo network (`php artisan demo:seed-network`, see `PROGRESS.md`) already has real Pair entries and achieved milestones at scale — open any member with pair rewards in Member Detail and compare their milestone rewards/dates against the Pair/Reward Milestones table's Left/Right thresholds (`DOMAIN_LOGIC.md` §7.1). `earnings:verify`'s `pair` check covers the arithmetic; it does not tell you _whether_ a member should have reached a milestone — for that, read their unused/consumed Left/Right counts on their own Pair/Reward page.
- **Income Booster:** genuinely reaching Level 1 (250+250 binary team under one member) needs a very large real network — the 500-member demo data does not reach it (documented, not a bug). To exercise the payout mechanics without a huge tree, create a `booster_qualifications` row directly via `php artisan tinker` for a test member (mirrors what `EvaluateBoosterQualification` would create) with a `qualified_at` a few months in the past, then run `ProcessBoosterPayouts` (§3) and check the 6 monthly `booster_payout_schedules` rows and wallet credits — `earnings:verify`'s `booster` check will confirm they are internally correct.
- **Monthly Draw:** use Super Admin → **Draw Settings** to set a small group size (e.g. 3) on a copy of the dev database so a group fills quickly, then run the Draw Group Generator and Executor jobs manually (§3) and check Draw Management for the winner, prize, and (when the winning member's Sponsor has ≥10 Directs) the upline benefit record. Remember: no wallet credit is created for a Draw win (§2).

---

## 6. Useful login credentials (dev database)

- **Super Admin:** `superadmin@goldwave.test` / `password`.
- **Any member:** log in at `/member/login` with their Customer ID as _both_ the username and the initial password.
- **A Future Rate EMI member with paid and pending instalments** (useful for Book-at-Current-Rate testing): `GWL52`, `GWL08`, `GWL41`, `GWL73` in the 500-member demo data.

Do not commit real production credentials anywhere — this section is for the local dev database only.
