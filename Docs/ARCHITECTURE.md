# System Architecture

## Status

- Status: Active
- Last verified: 13-09-2026 — updated against T-008's actual `app/` structure (`WalletLedgerService`'s hold/confirm/release debit-side), per this file's own Change Rule.
- Owner / evidence: Decided before any GoldWave business-logic code was written (per `Docs/advice.md`'s pre-coding checklist), against the schema finalized in T-002 (`Docs/DATABASE_SCHEMA.md`) and the rules in `Docs/DOMAIN_LOGIC.md`.

This file is the canonical home for **how** the code is organized — folder/namespace structure, layering, where business logic lives, how the compensation engine stays a single source of truth, job/event wiring, and the permission structure. It does not restate business rules (`DOMAIN_LOGIC.md`), page contents (`INSTRUCTIONS.md`), or schema (`DATABASE_SCHEMA.md`) — it says where the code that implements them goes.

## Layering (matches AGENTS.md's MVC principles, made concrete)

```
HTTP request
  → Route (routes/web.php, grouped by role: member / admin / super-admin)
  → Middleware (auth, role-gate — see Permissions below)
  → Controller (thin: validates via a Form Request, calls one Action/Service, returns an Inertia response)
  → Form Request (validation only — every field, every rule; controllers never inline-validate)
  → Action or Service (the business logic — see Compensation Engine below)
  → Model / Eloquent (persistence only — relationships, casts, scopes; no calculation methods on Models)
  → Inertia response (React page component receives typed props; no calculation happens in React — DOMAIN_LOGIC.md's Architecture Principle)
```

Controllers must stay thin enough that reading one tells you _what_ happens, not _how_ — the "how" lives in a Service/Action class named after the business operation (e.g. `ConfirmEmiInstallmentPayment`, not logic inlined in `EmiController@pay`).

## Folder / Namespace Structure

```
app/
  Actions/                  One class per single business operation, invokable (__invoke), the unit T-level tasks are built around
    Registration/           RegisterMember, ValidateSponsorCode, CalculateEmiRateBooking, ActivateMembershipOnPaymentConfirmed (T-003); GenerateEmiInstallments (T-005 — called by ActivateMembershipOnPaymentConfirmed right after the existing emi_schedules header-row creation, generates all emi_installments rows per the activation-date-anniversary cadence, DOMAIN_LOGIC.md §5 item 7)
    Payments/               ConfirmOnlinePayment, ApproveCashPayment, RejectCashPayment (T-003); ConfirmEmiInstallmentPayment (T-005 — mirrors ConfirmOnlinePayment/ApproveCashPayment for a specific emi_installments row instead of the registration payment, dispatches the same PaymentConfirmed Event so future Level Income/Pair Entry listeners (T-006/T-007) need no EMI-specific wiring)
    Auth/                   RequestLoginOtp, VerifyLoginOtp, LoginWithCustomerIdPassword, RequestPasswordResetOtp, VerifyPasswordResetOtp, SetNewPassword (T-003 — not in the original plan below; added once the Login & Authentication mechanism was designed, DOMAIN_LOGIC.md §2.2)
    Compensation/           CalculateLevelIncome (T-006 — Sponsor/Direct levels 1-12, `skipped` rows for a too-short chain or an inactive beneficiary, DOMAIN_LOGIC.md §6), CreatePairEntries + EvaluatePairMilestones (T-007 — team-size fan-out to every Binary Position ancestor + monthly incremental milestone consumption, DOMAIN_LOGIC.md §7), EvaluateBoosterQualification,
                             CalculatePurchaseRepurchaseIncome, CalculateStoreProfitDistribution
    Draw/                   GenerateDrawGroups, ExecuteMonthlyDraw
    Payout/                 SubmitPayoutRequest, ProcessPayoutRequest
    Store/                  ConfirmStoreSale, RecordStoreWalletTopup, DeductStoreWallet
    Profile/                SubmitPendingProfileFields, SubmitProfileChangeRequest, ReviewProfileChangeRequest
    DummyEntries/            GenerateDailyDummyEntries, AssignDummyEntryToLeader
  Contracts/                Interfaces for vendor-deferred integrations (T-003) — every Action depends on the interface, never a concrete gateway/channel class
    PaymentGatewayContract.php  createIntent()/verifyCallback(); bound to Services/Payments/FakePaymentGateway.php until a real gateway is chosen
    OtpChannelContract.php      send(); bound per-channel to Services/Otp/EmailOtpChannel.php (real, via Mail) and SmsOtpChannel.php (logs — no SMS vendor chosen yet)
  Services/
    WalletLedgerService.php   The ONLY class allowed to write wallet_ledger_entries or mutate members.wallet_balance/wallet_hold_amount. credit() front-loaded in T-006 (needed by CalculateLevelIncome per Compensation Engine rule 3 below); T-008 completed it with hold()/confirmHold()/releaseHold() (DOMAIN_LOGIC.md §11.1's Wallet Hold rule — a pending debit ledger row whose own status transitions to confirmed/reversed rather than a second row being created, since §22's "reverse/correct through linked transactions" governs fixing a mistake, not a hold's own declared lifecycle) and availableBalance() (wallet_balance minus wallet_hold_amount); T-009 (Payout request/processing) calls these rather than building its own debit logic
    StoreWalletService.php    The ONLY class allowed to write store_wallet_ledger_entries or mutate store_wallets.balance
    RuleVersionService.php    Reads the active rule_versions/rule_values snapshot (caches only the scalar id, never the Eloquent model); every Compensation/pricing Action depends on this, never on a raw Settings table lookup (T-003)
    OtpService.php            The ONE place otp_codes rows get created/verified (T-003) — every Auth Action goes through this rather than querying otp_codes directly
    SponsorChainResolver.php  Walks members.sponsor_id up to N levels — the ONE place Level Income, Purchase/Repurchase Income, and Store Profit Distribution all resolve their beneficiary chain from (T-004; also gates Directs View navigation via isSelfOrDescendant — no compensation Action consumes the level-walk yet, that starts at T-006)
    BinaryPlacementResolver.php  Implements the occupied-side traversal (DOMAIN_LOGIC.md §4.3) — the ONE place placement_parent_id gets decided (T-003; gained an equivalent isSelfOrDescendant check in T-004 for Tree View navigation; gained ancestorsWithSide() in T-007 — the unbounded Binary Position ancestor walk CreatePairEntries fans out to, DOMAIN_LOGIC.md §7)
    CustomerIdGenerator.php   Allocates sequential GWL0N… Customer IDs from a single locked counter row (T-003)
    Payments/FakePaymentGateway.php, Otp/EmailOtpChannel.php, Otp/SmsOtpChannel.php  Dev/default bindings for the Contracts above (T-003)
  Models/                   Eloquent models — relationships and casts only, no business methods
  Http/
    Controllers/
      Registration/         RegistrationController (public: show/validateSponsor/store/status) (T-003)
      Auth/                 GoldWaveLoginController, GoldWavePasswordResetController — Member-only login, distinct from the untouched Fortify-scaffolded generic /login (left in place as a plausible future Admin/Super Admin login path — the source spec never defines one) (T-003)
      Payments/             PaymentWebhookController (CSRF-exempt), PaymentDevSimulationController (non-production only) (T-003)
      SuperAdmin/           CashPaymentApprovalController (T-003, cash-registration approval only — store-scoped Admin cash approval deferred to T-014's store-initiated payment flow); SystemDashboard, AdminUsers, CompensationRuleVersions, DummyEntrySettings, DummyEntryAssignment, DrawSettings, MetalRates, PayoutTdsSettings, StoreManagement, StoreWalletManagement (not yet built)
      Member/               DirectsController, TreeController (T-004 — both serve Member and Super Admin from one controller each, gated by MemberPolicy); Dashboard, Profile, Membership, Emi, PaymentHistory, LevelIncome, PairReward, Booster, Draw, Wallet, Payout, Reports, Notifications (not yet built — T-015)
      Admin/                 StoreDashboard, StoreProfile, Repurchases, Inventory, StoreTransactions, StoreReports (not yet built — T-016)
    Requests/                One Form Request per write endpoint, named after the action (e.g. SubmitPayoutRequestRequest); T-003 added Registration/ and Auth/ subfolders
    Middleware/              EnsureRole (member/admin/super_admin — implemented T-003), EnsureStoreOwnership (Admin scoped to their own store_id, not yet built)
  Policies/                  One per model needing authorization (MemberPolicy, StorePolicy, PayoutRequestPolicy, ProfileChangeRequestPolicy, ...) — see Permissions below
  Jobs/                      Queued jobs — see Background Jobs below
  Events/ + Listeners/       PaymentConfirmed (T-003) → CalculateLevelIncomeOnPaymentConfirmed (T-006) + CreatePairEntriesOnPaymentConfirmed (T-007), both auto-discovered — Laravel 11+ event discovery, no manual Event::listen wiring needed, dispatched independently off the same confirmed payment; DrawResultPublished (broadcast), StoreSaleConfirmed — used to fan out compensation calculations without controllers/webhooks doing it inline
  Notifications/            Member/Admin notifications (profile-request status, cash-payment status, payout status)
```

**Public/pre-auth Controllers are organized by flow, not by role** (`Registration/`, `Auth/`, `Payments/`) — the Member/Admin/SuperAdmin role-folder split below only applies once a role context exists (after login/activation). This is a deliberate, documented deviation from this file's original Controllers layout (written before the Login & Authentication mechanism existed), not drift.

**Naming rule:** every non-trivial business operation is a named Action class, not a method buried in a fat Service. A Service only exists when several Actions need to share one clearly-owned piece of state or logic (the four Services above: wallet, store wallet, rule versions, chain resolution) — this avoids both "fat controller" and "fat service" anti-patterns without introducing a needless extra layer for every single operation.

## Compensation Engine — Single Source of Truth

This is the highest-risk part of the codebase (`DOMAIN_LOGIC.md` §0's "Income Calculation Integrity"), so its isolation rule is explicit:

1. **Every compensation Action reads configuration through `RuleVersionService`**, never a raw query against `rule_values` — this is what makes "store the rule_version_id used" (`DOMAIN_LOGIC.md` §22) automatic rather than something each Action has to remember.
2. **Every compensation Action resolves its beneficiary chain through `SponsorChainResolver`** — this is the one place that guarantees Level Income, Purchase/Repurchase Income, and Store Profit Distribution all walk `sponsor_id`, never `placement_parent_id` (`DOMAIN_LOGIC.md`'s Global Level-Based Income Rule). A compensation Action must never touch `placement_parent_id` directly.
3. **Every compensation Action writes its result by calling `WalletLedgerService::credit(...)`**, never by writing to `wallet_ledger_entries` or `members.wallet_balance` directly. This is what makes "never update a wallet balance without an associated ledger transaction" (`DOMAIN_LOGIC.md` §12) structurally true instead of a convention someone can forget.
4. **Idempotency is enforced at the data layer, not just in application logic**: `payments.idempotency_key` / `payments.provider_reference` are unique constraints (already in the schema), `draw_group_month_configs`/`draw_executions` have unique constraints on `(draw_group_id, cycle_month_no)`, `booster_payout_schedules` on `(booster_qualification_id, month_no)`, `pair_reward_transactions` on `(member_id, milestone_no)`, `store_profit_distributions` on `(store_sale_id, beneficiary_type)`. A duplicate attempt hits a DB constraint violation, not just a service-layer check that could have a race condition.
5. Web, Admin, and any future React Native app all call the **same Actions** (directly from Controllers today; behind an API layer later) — this is what makes "must not be implemented independently in multiple controllers/pages/mobile screens" (`DOMAIN_LOGIC.md` Architecture Principle) true by construction rather than by discipline.

## Background Jobs & Events

| DOMAIN_LOGIC.md §19 job              | Laravel implementation                                                                                                                                                                                               |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Daily Company Direct Generator       | Scheduled `Illuminate\Console\Scheduling` entry (`schedule:run` via cron) dispatching a queued Job                                                                                                                   |
| Draw Group Generator (15th, 00:00)   | Scheduled Job, `Asia/Kolkata` per `config/app.php`                                                                                                                                                                   |
| Monthly Draw Executor (15th, 12:00)  | Scheduled Job; on completion, fires a `DrawResultPublished` broadcast Event so an already-open Draw page updates without a refresh (`DOMAIN_LOGIC.md` §8.4's "Real-Time Draw Result Update")                         |
| EMI Due Processor                    | Scheduled Job — status transitions only, never activates eligibility itself                                                                                                                                          |
| Pair/Reward Monthly Evaluator (T-007) | Scheduled Job (`EvaluateMonthlyPairMilestones`, month-end) — iterates every beneficiary with unused `pair_entries` and runs `EvaluatePairMilestones` per member; guarded by `pair_reward_transactions`' unique constraint |
| Booster Payout Processor             | Scheduled Job, guarded by the `booster_payout_schedules` unique constraint                                                                                                                                           |
| Payment Webhook Processor            | A controller endpoint that verifies the signature, then dispatches a `PaymentConfirmed` Event — listeners trigger Level Income / EMI status update / Pair Entry creation, decoupled from the webhook response itself |
| Store Profit Processor               | Fired from a `StoreSaleConfirmed` Event the same way                                                                                                                                                                 |
| Report Export Worker                 | Queued Job per export request, never generated synchronously on the request thread                                                                                                                                   |
| Store Wallet Processor               | Runs inline inside the same DB transaction as the store sale/joining confirmation (not a separate async job) — DOMAIN_LOGIC.md §16.1 requires the deduction and the payment/purchase record to commit atomically     |
| Purchase/Repurchase Income Processor | Listener on `StoreSaleConfirmed`, same as Store Profit Processor                                                                                                                                                     |

**Real-time broadcast driver:** not yet chosen — recommend **Laravel Reverb** (first-party, self-hosted, no third-party account needed) over Pusher/Ably since it needs no external vendor relationship; final choice depends on hosting (a WebSocket-capable process needs to run continuously — revisit once `Docs/DEPLOYMENT.md` environment is decided). This is a technical choice, not a business one, so it is _not_ logged in `Docs/DOMAIN_LOGIC.md`'s open-items list — it can be swapped without any business-rule impact.

**Payment gateway / payout provider:** deliberately not chosen here — this is a vendor/cost decision for the user, not an architecture default I should pick. The Action layer (`ConfirmOnlinePayment`, `ProcessPayoutRequest`) should depend on a small interface (`PaymentGatewayContract`, `PayoutProviderContract`) so whichever provider is chosen later plugs in without touching compensation logic.

**Larastan/Carbon tooling note (discovered T-005, 13-09-2026):** on this project's Laravel 13.31 + Larastan 3.11 pairing, Larastan does not reliably infer a `datetime`/`date`-cast Eloquent attribute (declared via the `protected function casts(): array` method style) as `Carbon` — it falls back to treating it as `string`, so calling a Carbon method directly on it (e.g. `$member->activated_at->copy()`) fails static analysis even though it works correctly at runtime. Wrap the first access in `Illuminate\Support\Carbon::parse(...)` (safe regardless of whether the value is already a Carbon instance or a raw string) rather than reaching for `@phpstan-ignore`/a baseline entry. Every future Action/Controller touching a date-cast column for the first time (T-006 onward) should expect this and use the same pattern.

## Permissions / Security Structure

Three roles enforced server-side (`DOMAIN_LOGIC.md` §2), mapped to concrete Laravel primitives:

| Role                  | Middleware / Gate                                                      | Policy scoping                                                                                                                                                                                                                                                                                                                                     |
| --------------------- | ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `member`              | `EnsureRole:member` on all Member Portal routes                        | Can only view/act on their own `members` row and its relations — enforced in every Policy's `view`/`update` via `$member->user_id === auth()->id()`                                                                                                                                                                                                |
| `admin` (Store Owner) | `EnsureRole:admin` + `EnsureStoreOwnership` on all Admin/Store routes  | Every Store-scoped query is additionally constrained to `stores.owner_user_id === auth()->id()`; `StorePolicy`, `StoreSalePolicy`, etc. deny anything outside the assigned store. Company-wide member/compensation/draw/payout/settings routes are not registered under the Admin route group at all (defense in depth beyond just a Policy check) |
| `super_admin`         | `EnsureRole:super_admin` on Super Admin routes; otherwise unrestricted | Can additionally open any member's Directs/Tree view and any store — Policies allow `super_admin` to bypass the ownership check explicitly, not by omitting the check                                                                                                                                                                              |

`users.role` (added in T-002) is the source of truth for role checks — never inferred from which route was hit or from the presence of a `members` row (a `members` row can exist for a dummy/unassigned entry with no `users` row at all).

## Change Rules

- Update this file when the folder structure, layering rule, or job/event wiring actually changes in the code — keep it in sync with `app/`, don't let it drift into aspiration.
- Business rules stay in `DOMAIN_LOGIC.md`; this file only says where the code implementing them lives.
- Vendor/provider choices (payment gateway, payout provider, broadcast driver, hosting) are tracked here as open technical decisions, not duplicated in `DOMAIN_LOGIC.md`'s business-open-items list.
