# Domain Logic

## Status

- Status: Active
- Last verified: 09-09-2026
- Domain owner / evidence: Client specification `GoldWave_Claude_instructions.docx` (v2.0, consolidated relationship-income specification), fully migrated into this file. The `.docx` is now archival/reference-only — this file is authoritative for business rules; do not re-open the `.docx` for day-to-day work.

GoldWave is a binary MLM/team-based membership platform (gold/silver savings plans with EMI options) combined with a multi-store gold/silver retail operation. It is **not** a simple binary-tree UI: it requires two independent network relationships, payment-driven eligibility, auditable compensation calculations, incremental pair consumption, monthly draw cycles, booster schedules, a wallet/ledger, admin payout processing, company-generated dummy/direct entries, and multi-store purchase/repurchase income with profit distribution.

---

## 0. Foundational Principles (apply to every rule below)

- **Confirmed/eligible payments drive everything.** Only a confirmed/eligible payment (online payment verified via provider callback, or cash approved by Super Admin) triggers compensation, joining, or pair business. An initiated-but-unconfirmed transaction never counts.
- **Sponsor/Direct ≠ Binary Position.** These are two independent relationships and must be stored/derived independently. Never derive one from the other.
    - **Sponsor/Direct**: who personally invited/sponsored the member. Does not change based on where the member is later placed.
    - **Binary Position (placement)**: the actual Left/Right binary tree position the member occupies, which may be under a different member than their Sponsor.
- **Level Income (and every other "Level"-based rule) always resolves beneficiaries through the Sponsor/Direct chain, never through Binary Position/placement**, unless a rule explicitly states otherwise. This applies to: membership/joining income, EMI payment income, Purchase/Repurchase Income, and Store Profit Distribution.
- **EMI installment ≠ new joining.** A membership/joining transaction happens once, at entry. Later EMI installments are payment events only; they never create an additional joining.
- **Pair/reward entries are consumed incrementally and permanently.** Once an eligible Left/Right entry is consumed for a milestone/pair, it can never be reused for another milestone/pair.
- **No refunds, ever.** Payment reversal, refund, or cancellation of a confirmed payment is not supported anywhere in the application. A confirmed payment is final. Corrections use a controlled reversal/correction ledger transaction with a full audit trail — financial history is never silently deleted or overwritten.
- **Idempotency everywhere.** Duplicate payment webhooks, retried callbacks, and re-run scheduled jobs must never create duplicate joinings, income, payouts, or ledger entries.
- **Centralized, versioned configuration.** All rates, thresholds, minimums, and tax/TDS values live in Super Admin Settings, are never hard-coded, and changes must not silently alter the calculation basis of already-finalized historical transactions (store a rule/config version or snapshot with each calculation).
- **One authoritative backend implementation.** All financial/compensation calculations live in the Laravel backend only. Web (Inertia/React), Admin views, and the future React Native app must read the same finalized result — never recalculate independently.

---

## 1. Terminology

| Term             | Meaning to implement                                                                                                                                                                                                      |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sponsor / Direct | The member who personally invited/sponsored another member. Does not change because placement may occur elsewhere.                                                                                                        |
| Binary Position  | The actual binary position where a member is placed: Left or Right under another member (whose binary downline the member is under).                                                                                      |
| Downline         | Members below a member in the binary placement structure.                                                                                                                                                                 |
| Directs          | Members personally sponsored by the current member, independent of binary placement.                                                                                                                                      |
| Eligible payment | A payment that has reached the confirmed/eligible state and can participate in compensation rules.                                                                                                                        |
| Joining          | The initial membership transaction. One-time plans create one joining; EMI plans create the membership once, and later installments are payment events.                                                                   |
| Pair             | Left/Right matching of eligible new entries for Reward/Pair Income.                                                                                                                                                       |
| Upline           | Ancestors in the applicable relationship. Level Income, Store Profit Distribution, and Purchase/Repurchase Upline Income all use the **Sponsor/Direct** chain. Draw Upline Benefit uses the member's direct Sponsor only. |
| Company direct   | A direct entry owned/sponsored by the company/Super Admin, created by the Daily Dynamic Company Direct Entries engine.                                                                                                    |
| Store owner      | The owner associated with a managed store. Store profit distribution: Owner 2%, L1 0.5%, L2 0.25%, L3 0.25% (Sponsor/Direct chain).                                                                                       |

---

## 2. Roles & Access Model

| Role                  | Primary responsibility                                                                                                                                                                                                                                                                                                                                                                      | Access boundary                                                                                         |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Customer / Member     | Own profile, plan, payments, network, income, draw, booster, wallet, and reports. Can view their own full downline tree.                                                                                                                                                                                                                                                                    | Own data + permitted network visibility.                                                                |
| Admin / Store Owner   | Manages the assigned store's operations: repurchases/sales, inventory, store reports. **(RESOLVED 12-09-2026 — user confirmation)** A Store Owner is also a full network Member of GoldWave (own Customer ID, sponsor/placement, and wallet) in addition to holding the Admin/store-operations account; their 2% Store Profit Distribution share (§16.4) is credited to that Member wallet. | Assigned store only. **No** company-wide member, compensation, draw, payout, or system-settings access. |
| Super Admin / Company | Full company/system control: members, network views, compensation, payments, payouts, draw, rates, stores, dummy entries, configuration.                                                                                                                                                                                                                                                    | Full system control.                                                                                    |
| System Scheduler      | Automated grouping, draw execution, daily dummy-entry creation, recurring/periodic processing.                                                                                                                                                                                                                                                                                              | Backend process; no login UI.                                                                           |

**Global Role Rule:** Super Admin represents the Company and has full application-level control. Admin represents the Store Owner and is restricted to the assigned store/store-level operations — Admin cannot manage company-wide members, compensation, draw, payouts, or system settings. Super Admin can view and manage all members, including any member's Directs View and Binary Position Tree View. Any store operational detail not covered below remains **PENDING CLIENT DECISION**.

### 2.1 Global authentication / registration rules

1. Registration begins only after a sponsor/invite code is validated; invalid code blocks continuation. **Sponsor-must-be-active rule (RESOLVED 12-09-2026 — user confirmation):** if the sponsor's own member account is not currently Active — regardless of the reason (own EMI/payment pending, Super Admin suspension, or any other cause) — the invite/sponsor code is treated as invalid and registration under it is blocked, exactly like an invalid code. **No new member may register under an inactive sponsor.** This check is on the sponsor's current status at the moment of registration, re-evaluated every time (a sponsor who later becomes Active can sponsor new members again from that point on).
2. Sponsor/direct name is shown after successful validation.
3. User selects Left or Right placement side.
4. User enters mobile, email, name, and plan selection. **Both mobile and email are mandatory fields** (RESOLVED 12-09-2026 — user confirmation) — both are needed later for login (§2.2).
5. Payment is initiated using the selected payment mode (Online or Cash). **(RESOLVED 12-09-2026 — user confirmation)** This Online/Cash choice works identically regardless of plan type — an EMI plan's (A–D) first installment can be paid Online right on the registration page, or Cash can be selected instead, exactly the same as the one-time Plans E/F's payment-mode selection. Do not restrict Cash to one-time plans or Online-only to EMI plans.
6. Registration becomes **active only after payment confirmation/eligibility**. For Cash, registration stays inactive until Super Admin confirms receipt and activates the member; the activating operator is recorded.
7. System generates a unique Customer ID in the `GWL01…` format (sequential/company-controlled, never recycled).
8. Post-registration pending fields can be completed only once by the member; after that, changes require a request to Super Admin (see §14).

### 2.2 Login & Authentication (RESOLVED 12-09-2026 — user confirmation; no code exists yet — needs go-ahead before T-003)

The source document never specified this flow (it only listed "Login" and "password/account recovery" as page names, with no mechanism) — this section is a genuine addition, not a source clarification, decided directly with the user. **This is the standing rule for all future work, not just T-003.**

- **Login is only possible after membership activation.** A member in a pending state (Cash payment awaiting Super Admin confirmation, or any other pre-activation state) cannot log in at all — there is no "view my pending status" login.
- **Login identifier:** either the member's registered **mobile number or email** — both work interchangeably.
- **Two login methods are both supported side by side:**
    1. **OTP login** — the member requests an OTP (sent via SMS to the registered mobile, or email, whichever they used to identify themselves) and logs in by entering it. No password needed for this path.
    2. **User ID + Password login** — User ID is the member's **Customer ID** (`GWL01…`). **Initial/default password is the Customer ID itself** (i.e. the freshly-activated member's password starts out equal to their own Customer ID value) — this is a deliberate simple default, not a security placeholder to be "fixed" later without being asked.
- **Password set/reset requires OTP first:** a member can only set or change their User ID+Password login password by first successfully authenticating via OTP (method 1). There is no separate "forgot password" flow that bypasses OTP — OTP verification is the only gate for password changes.
- Both login methods remain available side by side going forward (a member is never forced to abandon OTP login just because they've set a password, or vice versa).

---

## 3. Membership / Entry Plans

**(RESOLVED 12-09-2026 — client-provided `GoldWave_Claude_instructions_2.docx`, Version 2.0)** The plan table and product entitlement below supersede the earlier 5-plan table and the "CLIENT CONFIRMATION REQUIRED" note that used to sit here. This is a genuine scope change from the client, not a documentation-only clarification: it adds a new Plan D and a dual Current/Future Rate Booking mechanism that did not exist before. **No code exists yet for this area (T-005 is still Pending), so this is a documentation update only — do not start implementing the schema/EMI-service changes below without the user's separate go-ahead**, per the standing instruction that a resolved item is never itself permission to code.

| Plan                                      | Amount        | Schedule        | Entitlement (finalized)                                          |
| ----------------------------------------- | ------------- | --------------- | ---------------------------------------------------------------- |
| A                                         | ₹1,000/month  | 20 months (EMI) | 100gm Silver Jewellery (fixed weight)                            |
| B                                         | ₹3,000/month  | 10 months (EMI) | 100gm Silver Jewellery (fixed weight)                            |
| C                                         | ₹5,000/month  | 10 months (EMI) | 5gm Gold Jewellery (fixed weight)                                |
| D **(NEW plan)**                          | ₹10,000/month | 10 months (EMI) | 10gm Gold Jewellery (fixed weight)                               |
| E _(was "D" in the pre-12-09-2026 table)_ | ₹20,000       | One-time        | Choice of Silver Jewellery at the applicable current silver rate |
| F _(was "E" in the pre-12-09-2026 table)_ | ₹50,000       | One-time        | Choice of Gold Jewellery at the applicable current gold rate     |

All benefits are **jewellery items, not solid/bullion metal**. Plans E and F are one-time (no EMI, no rate-booking choice — the applicable rate is simply the rate on the date the one-time payment is confirmed).

### 3.0 Rate Booking — Current vs. Future (EMI plans A–D only)

**(RESOLVED 12-09-2026 — client-provided spec v2.0)** For every EMI plan (A/B/C/D), the member must choose exactly one of two rate-booking methods at registration. This selection is **mandatory** and must be stored with the membership/EMI record — it cannot be changed after registration.

- **Current Rate Booking:** the jewellery weight is fixed (per the plan table above); the metal rate is locked at the current rate on the booking date; the EMI amount is computed by this formula and does **not** change for the life of the schedule:
    - `Total Current-Rate Jewellery Value = configured metal rate × plan's fixed jewellery weight`
    - `Maintenance Cost = 1% of Total Current-Rate Jewellery Value`
    - `EMI Amount = (Total Current-Rate Jewellery Value ÷ Number of EMIs) + Maintenance Cost`
    - **Worked example (source-given):** Plan A, 100gm Silver = 10 tola, silver rate ₹3,500/tola → Total Value = ₹35,000; Maintenance Cost = ₹350; over 20 EMIs, EMI Amount = (₹35,000 ÷ 20) + ₹350 = ₹1,750 + ₹350 = **₹2,100/month**.
    - At final EMI completion, the member receives the fixed jewellery weight (100gm Silver, 5gm Gold, etc. per plan) — the weight never changes even if the metal rate moved during the schedule.
- **Future Rate Booking:** no jewellery weight or rate is locked at booking. Instead:
    - `Future Jewellery Commitment (₹) = base EMI amount × Number of EMIs` (i.e. exactly the plan's stated amount — Plan A = ₹1,000 × 20 = ₹20,000; Plan D = ₹10,000 × 10 = ₹1,00,000, etc. — see the plan table's Amount/Schedule columns).
    - No 1% maintenance cost is added to Future Rate Booking, unless a future Super Admin configuration explicitly adds one.
    - At final EMI completion/delivery, the member receives jewellery **of that committed rupee value**, purchased at whatever the applicable Gold/Silver rate is **on the delivery date** — so the delivered weight varies with the rate at that time, unlike Current Rate Booking's fixed weight.
- Super Admin continues to manage Gold and Silver rates together on one Rate Settings page (rate/reference history with effective dates). For Current Rate Booking, the exact rate used and the resulting EMI amount must be recorded against the membership/EMI schedule so historical calculations stay reproducible even if rates change later.
- The Member EMI page must show: selected plan, selected rate-booking method, total commitment, and per-installment: due month/date, the calculated installment amount, status, and the applicable rate-booking details.

### 3.1 Registration business logic

1. Validate invitation code; block continuation if invalid.
2. Show sponsor/direct name after successful validation.
3. Check requested binary position side (Left/Right).
4. Place the member per the Binary Position Algorithm (§4.3) — the new entry always goes to the selected L/R side even if the empty position is many levels down.
5. **For any EMI plan (A/B/C/D):** require the member to select Current Rate Booking or Future Rate Booking (§3.0) before proceeding; this is mandatory and must be stored with the membership/EMI record. The selected option and the resulting EMI amount/future commitment must be shown in the order/payment summary. Plans E/F have no rate-booking step.
6. Create a pending registration/order record before payment.
7. **Online payment:** redirect/open gateway and wait for a verified callback/webhook.
8. **Cash payment:** create a Cash Pending/Approval workflow; membership is **not** activated merely because the user selected Cash. Admin/Super Admin changes the customer's status to Active, and a flag records who activated it.
9. After confirmed payment/approval: activate membership, generate Customer ID, create EMI schedule if applicable (using the EMI amount determined by the selected rate-booking option), trigger eligible compensation calculations.
10. Record the Sponsor relation and Placement relation as two separate, independent records.
11. Only from this point (activation) onward can the member log in — see §2.2 for the login mechanism (OTP via mobile/email, or Customer-ID + password with a Customer-ID-equal initial password).

### 3.2 Customer ID

- Unique, sequential/company-controlled identifier (e.g. `GWL01`, `GWL10`, `GWL11`).
- Never recycled.
- Displayed on member profile, draw lists, reports, and search.

---

## 4. Sponsor, Direct & Binary Position

### 4.1 Directs View (Sponsor/Direct relationship only — never Binary Position)

- Header always shows the **logged-in member's** Name and Customer ID (constant throughout navigation).
- Below the header: the logged-in member's personal direct members.
- Clicking any Direct card shows a Selected Member Card (name + Customer ID) below the header, and that member's own directs below the card.
- Any displayed Direct card can be clicked to make that member the new Selected Member (recursive navigation), to any depth.
- Must **not** use or display Binary Position parent-child relationships.
- Super Admin can open this view for **any** member (not only their own).
- **Search by Customer ID (added 13-09-2026):** a member may search for and jump directly to any Customer ID that is within their own Sponsor/Direct downline (i.e., anyone the recursive navigation above could eventually reach). A Customer ID that exists but sits outside the searching member's downline — "cross-leg," on some other member's branch entirely — is rejected with the same generic error as a Customer ID that does not exist at all; the search must never confirm or deny that a given Customer ID exists outside the viewer's own authorized scope. Super Admin's search is unrestricted (any Customer ID), consistent with Super Admin being able to open this view for any member.

### 4.2 Tree View (Binary Position only — never Sponsor/Direct)

- Header always shows the logged-in member's Name and Customer ID (constant throughout navigation).
- Logged-in member starts as the Root Member, with Left/Right placement branches shown below.
- Clicking any node makes it the Selected Member; a Selected Member Card appears below the header; that member becomes the new Root Member with their own Left/Right branches, recursively.
- Each child can be expanded to view its own Left/Right branches. Zoom, pan, and navigation must be supported.
- Node cards show Customer ID, Name, and Status.
- **All node/member representations must use rectangular cards only — circular node designs must never be used anywhere in the application.**
- Represents Binary Position/Placement only, never Sponsor/Direct.
- Super Admin can open this view for **any** member.
- **Search by Customer ID (added 13-09-2026):** same rule as §4.1's Directs View search, applied to the Binary Position downline instead — only a Customer ID within the searching member's own placement downline resolves; a "cross-leg" Customer ID (exists, but on a different placement branch) is rejected identically to an unknown one. Super Admin's search is unrestricted.

### 4.3 Binary Position Algorithm

1. Capture `sponsor_id` from the invitation code.
2. Capture the requested binary position side (Left/Right).
3. Start from the sponsor's selected Left or Right branch.
4. If the position is empty, place the member there.
5. **If occupied, continue traversing straight down the same selected side** (Left → Left → Left… or Right → Right → Right…) until the first empty position is found, regardless of depth. Manual alternate-side placement is not allowed — the member is always placed on the side they selected.
6. Persist `placement_parent_id` and `placement_side` independently from `sponsor_id`.
7. For additional direct members beyond what fits directly under the sponsor, preserve `sponsor_id` as the original sponsor even though `placement_parent_id` belongs to a different member (this is expected and correct — see §0 "Sponsor/Direct ≠ Binary Position").

---

## 5. EMI Management & Gold/Silver Product Allocation

1. Create the full installment schedule when an EMI membership is activated, using the EMI amount determined by the member's selected rate-booking option (§3.0 — Current Rate Booking or Future Rate Booking).
2. Each successful monthly payment becomes an **eligible payment event** (never a new joining).
3. A payment event may generate Level Income per §6, using the configured percentage and the Sponsor/Direct chain.
4. **For Pair/Reward qualification**, use the applicable plan-specific completed-EMI requirement (see §7.3 for the finalized per-plan counts — A=6, B=2, C=2, D=1).
5. **Rate Management Rule:** Super Admin manages Gold and Silver rates together on one Rate Settings page. The page stores rate/reference history with effective dates. For Current Rate Booking, the exact rate used for the selected metal/weight and the resulting EMI amount must be recorded with the membership/EMI schedule so historical calculations stay reproducible even if rates change later. The applicable product entitlement follows the finalized Membership Plan configuration (§3 — RESOLVED).
6. Store product inventory is a separate module from membership benefit allocation — do not conflate the two.
7. **Installment due-date cadence (RESOLVED 13-09-2026 — user confirmation; no source spec existed for this):** installment 1's due date is the membership's activation date itself; each subsequent installment's due date is the same day-of-month as the activation date, one calendar month later (an "activation-date anniversary" schedule) — e.g. activation on 17-02-2026 produces due dates 17-02-2026, 17-03-2026, 17-04-2026, … regardless of which installments are paid early or late (the schedule does not drift based on actual payment dates). **Overdue rule:** there is no grace period — a `due` installment becomes `overdue` starting the very next calendar day after its due date if still unpaid.
8. **Installment payment ordering (RESOLVED 13-09-2026 — user confirmation; no source spec existed for this):** a member may only initiate payment for their schedule's single earliest-unpaid installment — no skip-ahead/advance payment of a later `upcoming` installment while an earlier one is still unpaid.

---

## 6. Level Income — Detailed Calculation Logic

**Eligible base:** for one-time plans, the entry amount; for EMI plans, the actual confirmed monthly installment amount.

| Level | Rate      |
| ----- | --------- |
| 1     | 5%        |
| 2     | 2%        |
| 3     | 2%        |
| 4–8   | 1% each   |
| 9–12  | 0.5% each |

**Level Income Chain Rule:** Levels 1–12 always follow the Sponsor/Direct chain. Level 1 = the member's direct Sponsor, Level 2 = that Sponsor's Sponsor, and so on up to Level 12. Binary Position/placement and its upline chain are never used for Level Income.

### 6.1 Processing steps

1. Receive verified payment event.
2. Determine event type: one-time joining or EMI installment.
3. Determine eligible amount.
4. Resolve Sponsor/Direct levels up to Level 12 by following the member's Sponsor → Sponsor chain.
5. Read the configured percentage for each level.
6. Calculate income = eligible amount × level rate.
7. Create one income ledger transaction per qualifying Sponsor/Direct beneficiary, linked to the source payment/joining transaction.
8. Update member wallet/ledger balances.
9. If a level has no eligible beneficiary (chain too short, upline inactive, etc.), record a skipped/non-payable reason for auditability — do not silently drop it.

### 6.2 Example (illustration of source percentages, not an additional rule)

For a confirmed ₹5,000 payment: Level 1 → direct Sponsor at 5%; Level 2 → that Sponsor's Sponsor at 2%; Level 3 → next Sponsor at 2%; Levels 4–8 → successive Sponsors at 1% each; Levels 9–12 → successive Sponsors at 0.5% each.

---

## 7. Reward / Pair Income

**Core rule:** each eligible joining creates ₹50 of pair value. Pairing uses Left and Right eligible business. Previously consumed entries can never be reused.

**Pair/Reward Beneficiary Chain Rule (RESOLVED 13-09-2026 — user confirmation):** Left/Right pair-eligible business is a **team-size** count, never a "direct-to-direct"/immediate-child-only one. Every ancestor in the member's entire Binary Position chain — unbounded depth, not just the immediate placement parent — accumulates one pair-eligible entry on whichever of their two legs (Left/Right) the new eligible joining's subtree falls under. This mirrors Income Booster's "binary team, no direct needed" full-subtree semantics (§9), applied here to individually consumable, non-reusable entries rather than a live headcount recompute — §7.2's incremental-consumption/carry-forward rule needs durable per-beneficiary state a live team-size snapshot alone can't provide. (An immediate-parent-only reading was considered and rejected: since Binary Position placement is strictly two children per member, it would make milestone 2 onward — 50L/50R and beyond — mathematically unreachable.)

### 7.1 Milestones

Every milestone's "Min. Direct Members" default is **2** (Super-Admin-configurable — see §7.3).

| Milestone | Min. Direct Members (default) | Left      | Right     | Reward                                                                                                   |
| --------- | ----------------------------- | --------- | --------- | -------------------------------------------------------------------------------------------------------- |
| 1         | 2                             | 5         | 5         | ₹500                                                                                                     |
| 2         | 2                             | 50        | 50        | ₹5,000                                                                                                   |
| 3         | 2                             | 250       | 250       | ₹25,000                                                                                                  |
| 4         | 2                             | 500       | 500       | ₹50,000                                                                                                  |
| 5         | 2                             | 1,000     | 1,000     | ₹1,00,000                                                                                                |
| 6         | 2                             | 2,000     | 2,000     | ₹2,00,000                                                                                                |
| 7         | 2                             | 5,000     | 5,000     | ₹5,00,000                                                                                                |
| 8         | 2                             | 10,000    | 10,000    | ₹10,00,000                                                                                               |
| 9         | 2                             | 20,000    | 20,000    | ₹20,00,000                                                                                               |
| 10        | 2                             | 40,000    | 40,000    | ₹40,00,000                                                                                               |
| 11        | 2                             | 80,000    | 80,000    | ₹80,00,000                                                                                               |
| 12        | 2                             | 160,000   | 160,000   | ₹160,00,000                                                                                              |
| 13        | 2                             | 320,000   | 320,000   | ₹320,00,000                                                                                              |
| 14        | 2                             | 640,000   | 640,000   | ₹640,00,000                                                                                              |
| 15        | 2                             | 1,280,000 | 1,280,000 | ₹128,00,000 (RESOLVED — see §7.3 conflict note; keep this value unless the client separately changes it) |

### 7.2 Incremental counting

1. Maintain separate unused/consumed eligible-entry counts for Left and Right.
2. New eligible entries are added to the unused pool.
3. Evaluate the next unachieved milestone.
4. Consume only the new/additional entries needed for that milestone.
5. Create the reward transaction and mark the consumed entries as used for that milestone — never reuse them for a later milestone.
6. Continue evaluating higher milestones while enough additional eligible entries exist (a single monthly run may cross multiple milestones).

### 7.3 Qualification & monthly calculation

- **Minimum Direct Members:** **(RESOLVED 12-09-2026 — user confirmation)** every one of the 15 milestones requires a minimum of **2** Direct Members by default — a flat value, not a per-milestone progression. This default must be **Super-Admin-configurable** (Settings module) and dynamic — the client/Super Admin can change it in future — never hard-coded.
- **Pair Qualification EMI Rule:** the ₹1,000 plan (A) requires a minimum of **6** completed EMIs; the ₹3,000 (B) and ₹5,000 (C) plans require a minimum of **2** completed EMIs. **Plan D (₹10,000/10 months) — RESOLVED 12-09-2026, user confirmation:** default of **1** completed EMI, Super-Admin-configurable/dynamic (same pattern as the other plans — a real default is seeded, and Super Admin can change it later; not left unconfigured). Only after the applicable EMI requirement is met does the EMI membership count as a full eligible joining for Pair/Reward purposes.
- Pair income is calculated **at the end of every month**, over all valid/verified eligible pairs completed by month-end, at ₹50 per pair.
- **Carry forward:** unpaired eligible Left/Right business at month-end carries forward and combines with newly eligible business next month.
- **No payout cap** — daily or monthly — on Pair Income.
- Current Pair Income TDS/Tax is **0%** unless changed by Super Admin configuration.
- (RESOLVED) Milestone 15 reward is ₹128,00,000 against 1,280,000 Left/Right, per the corrected table above — the earlier "₹120,00,000" figure in the source summary was superseded.

### 7.4 Centralized Super Admin Settings (applies across Level/Pair/Booster/Tax)

The Settings module must expose, at minimum: milestone-wise minimum Direct Member requirements, pair value, EMI qualification requirements per plan, income/commission percentages, tax/TDS percentages, and any other configurable reward/qualification/threshold/limit value defined in this document. Configuration changes must be validated, authorized, and versioned/history-tracked so the value used for any historical calculation can always be determined.

---

## 8. Monthly Draw

### 8.1 Grouping

- On the **15th at 12:00 AM**, the system generates groups from eligible company members using the configured group size (default/example 200; Super Admin can configure 200, 300, 500, or another allowed number).
- Group 1 = first N Customer IDs, Group 2 = next N, etc.
- Entries occurring on the 15th **after** the grouping cutoff are excluded from that day's draw.
- Groups remain traceable for the full draw cycle.

### 8.2 Group Draw Cycle

- Each generated group has an **independent 20-month Draw Cycle**, starting the month it is created.
- The system maintains that group's complete eligible-member snapshot for the full 20 months.
- One eligible member is selected as winner and removed from that group's remaining pool each monthly draw; the group continues its own monthly draw until all 20 months complete.
- Different groups may start in different months — track each group's current draw month/remaining months independently.

### 8.3 Group-wise Prize Configuration

- Super Admin configures Prize Item/Name and Prize Value **separately per group and per Draw Month** within its 20-month cycle.
- Current business rule: the **first 15 months** of every group's cycle use **Silver** items/prizes; the **final 5 months** use **Gold** items/prizes.
- Historical completed draw months retain the exact Prize Item/Name and Prize Value used at the time of that draw (immutable snapshot).

### 8.4 Draw execution (12:00 PM on the 15th)

1. Process groups sequentially.
2. For each group, retrieve eligible member table IDs.
3. Use a **secure random selection mechanism** to choose one eligible member.
4. Fetch winner data and create an **immutable draw-result record**.
5. **Slot-Machine Draw Animation:** after the backend securely determines the winner via RNG, present the result through a slot-machine-style animation. The number of visual digit slots dynamically matches the digit count of the highest/last Customer ID in the selected group (e.g. group ending at Customer ID 200 → 3 slots; 2,000 → 4 slots; 12,500 → 5 slots). The animation is purely visual — it must never generate, alter, or determine the actual winner; the backend RNG result is final and authoritative.
6. **Real-Time Draw Result Update:** members/admins who already have the Draw Page open before execution time must receive the result automatically via the application's real-time update mechanism (no manual refresh). The slot-machine animation starts automatically on receipt of the real-time result.
7. Remove the winner from subsequent draws for that group's remaining cycle.
8. Evaluate the eligible upline draw benefit (§8.5).
9. Publish/store the result for member/admin reporting.

### 8.5 Upline draw benefit

If the winner's Sponsor/Direct (the member who personally sponsored/joined them) has **at least 10 Direct Members**, that one direct Sponsor receives the same item/benefit. No additional upline levels receive the draw benefit. If there is no qualifying upline, the winner still receives their own prize and the upline benefit is simply skipped.

### 8.6 Draw safety

- Group generation must be idempotent.
- Draw execution must be idempotent.
- A group cannot receive two winners for the same draw cycle unless explicitly configured otherwise.
- Winner selection must have an auditable random-selection record.
- Any admin correction creates a reversal/correction audit record — never overwrite draw history.

### 8.7 Draw eligibility — EMI plans (RESOLVED 12-09-2026 — client spec v2.0)

For an EMI membership (Plans A–D, §3), the member becomes eligible for the Monthly Draw only after completing the plan's minimum required EMIs:

| Plan           | Completed EMIs required for Draw eligibility |
| -------------- | -------------------------------------------- |
| A (₹1,000×20)  | 6                                            |
| B (₹3,000×10)  | 2                                            |
| C (₹5,000×10)  | 2                                            |
| D (₹10,000×10) | 1                                            |

Only confirmed/eligible EMI payments count toward this threshold. **This Draw-eligibility rule is a separate rule from Pair/Reward EMI qualification (§7.3)** — the source states this explicitly, even though Plans A/B/C happen to use the same numeric thresholds for both rules; do not merge the two checks into one code path, since Plan D's Draw threshold (1) and Pair/Reward threshold (still unconfigured — §7.3, §21) are already known to diverge. One-time Plans E/F require full payment (no EMI concept applies).

---

## 9. Income Booster

**(RESOLVED 12-09-2026 — client-provided spec v2.0)** Duration changed from 3 to **6 consecutive months** per level, and the table now specifies the binary team's Left/Right split explicitly. Team size is a **pure binary-team count** ("binary team, no direct needed") — it is evaluated from the member's Binary Position downline, independent of the Direct-Members count in the "Min. Directs" column.

| Level | Min. Directs | Team size (binary, no direct needed) | Team split (Left–Right) | Monthly benefit | Duration | Total     |
| ----- | ------------ | ------------------------------------ | ----------------------- | --------------- | -------- | --------- |
| 1     | 10           | 500                                  | 250–250                 | ₹5,000          | 6 months | ₹30,000   |
| 2     | 20           | 1,500                                | 750–750                 | ₹20,000         | 6 months | ₹1,20,000 |
| 3     | 30           | 3,000                                | 1,500–1,500             | ₹60,000         | 6 months | ₹3,60,000 |

_(The source docx shows the Level 3 total as "₹36,0,000", which is a comma-placement typo for ₹3,60,000 = ₹60,000 × 6 — verified by simple multiplication; use ₹3,60,000.)_

**Implementation note for T-011 (added 13-09-2026, verified via background review during T-007):** this "Team size" is the same full-subtree, unbounded-depth concept as Pair/Reward's §7 team-size count, but needed here as a **live recompute** rather than T-007's permanent consumable ledger (`pair_entries`) — Booster has no "never reuse a consumed entry" requirement, it just re-measures current subtree size against a threshold each evaluation. Introduce a dedicated, reusable counting service (e.g. `BinaryTeamSizeCounter`) at T-011 rather than reusing T-007's `pair_entries`-based approach, which is idempotency/consumption-ledger machinery this doesn't need. Confirmed no other rule in this document needs a recursive "directs-of-directs" metric distinct from either plain `sponsor_id` directs-count or this full-subtree team-size count — do not build one speculatively.

### 9.1 Calculation flow

1. Recalculate direct count after eligible member additions.
2. Recalculate total team size after eligible placement additions, split by Left/Right binary team.
3. Evaluate **every** booster level the member newly qualifies for (not only the highest — see concurrency resolution below), checking both the Min. Directs and the Team size (with its Left/Right split) thresholds for that level.
4. Create a 6-month booster schedule **per newly-qualified level**.
5. At each scheduled payout, verify continuing eligibility only if the business rule requires it (see qualification rule below).
6. Create the benefit ledger entry and payout eligibility record.
7. Prevent duplicate monthly benefit records.

**Qualification rule:** qualification is required only **once per level**. Once a member qualifies for a booster level, they receive that level's applicable benefit for **6 consecutive months** regardless of whether they continue to meet that level's qualifying thresholds during those 6 months.

**Concurrency rule — (RESOLVED — user confirmation, 12-09-2026):** a member CAN hold multiple concurrent booster schedules across all three levels at once, if/when they separately qualify for each. Example: a member qualifies for Level 1 in month 1 (schedule runs months 1–6); if they newly cross the Level 2 threshold in month 2, a second, independent Level-2 schedule (months 2–7) starts alongside the still-running Level-1 schedule — the two are not merged and neither is cancelled. The same applies if Level 3 is reached while Level 1 and/or Level 2 schedules are still paying out. Each level's 6-month schedule and duplicate-payout guard (step 7 above) is tracked independently per level. _(Note: the client's spec v2.0 document itself still contains the older "evaluate the highest qualifying booster level" wording, unchanged from v1 — this concurrency rule stands on the user's direct verbal confirmation given in this session, which takes precedence over that leftover ambiguous wording; it has not been separately re-confirmed in writing by the client since the v2.0 docx.)_

---

## 10. Payment In

### 10.1 Online payment logic

1. Create a payment intent with a unique internal transaction reference.
2. Send amount and context to the payment provider.
3. **Never activate based on frontend success alone.**
4. Verify the provider callback/webhook/signature.
5. Mark payment Paid only after verification.
6. Create the eligible payment event; trigger level income / pair qualification / product / account updates.
7. Store the provider reference and timestamps.
8. Make the callback idempotent — repeated callbacks must never duplicate payment or income.

### 10.2 Cash payment logic

1. Member selects Cash and submits a payment request → creates a Cash Pending record.
2. Admin/Super Admin verifies the physical receipt.
3. On approval: mark Paid, continue normal eligibility processing.
4. On rejection/cancellation: retain the audit trail, generate no compensation.

---

## 11. Payment Out / Payout

### 11.1 Member-side rules

- **Minimum Payout Request:** a member can submit a request only when the amount meets the configured minimum (default **₹500**, configurable by Super Admin).
- A member can only **submit a payout request** — never directly withdraw, transfer, or pay themselves from the wallet. The system validates balance, minimum amount, beneficiary/account verification, and applicable holds before creating the request.
- Payout requests are processed **monthly** by Super Admin; every request requires Super Admin review regardless of eligibility.
- **Wallet Hold:** the requested amount is placed On Hold during processing and released when the payout completes or the request is rejected/cancelled.
- **Withdrawal Processing Fee:** not currently defined; Super Admin Settings will expose an optional fee field — defaults to 0 until configured.

### 11.2 Super Admin processing

1. Review pending payout requests; approve/process, reject, or cancel.
2. On approval, create a payout record with an **immutable amount snapshot**, beneficiary snapshot, and source withdrawal/request reference.
3. **Payment modes:** Cheque, GPay/UPI, Bank Transfer, or an integrated in-app payout/disbursement provider where available. A collection/payment gateway used for member payments is a separate capability from a payout/disbursement provider — never assume one implies the other.
4. **Bulk/Batch Payout:** Super Admin can select multiple eligible requests and process them together via a supported bulk/batch mechanism; each member payout still gets its own amount, beneficiary, mode, reference, status, and immutable audit record — even when the provider processes them as one batch.
5. Record the payment mode and reference (bank transaction/reference/UTR, or cheque number/details).
6. On success: mark the individual payout Processed, create the corresponding debit ledger transaction.
7. On failure: mark Failed, keep the member's balance consistent — a failed payout must never create a permanent debit.
8. **Bank details** are manually verified by Super Admin; a cancelled cheque/passbook image may serve as supporting proof.
9. **TDS/Tax:** Super Admin-configurable percentage, default 0% until finalized.
10. Confirmed payouts are final — no refund/reversal (see §0).

---

## 12. Wallet / Ledger

Wallet is the financial truth layer connecting income, benefits, and payouts.

- **Credits:** Level Income, Reward/Pair Income, Income Booster, Draw/Upline Draw Benefit (where monetary), Store Profit Distribution.
- **Debits:** Member payout (amount paid to the member by Super Admin).

### 12.1 Ledger transaction fields

| Field                        | Purpose                                               |
| ---------------------------- | ----------------------------------------------------- |
| Transaction ID               | Unique immutable reference                            |
| Member ID                    | Owner of the ledger entry                             |
| Type                         | Credit/debit/category                                 |
| Amount                       | Transaction value                                     |
| Source reference             | Payment, income calculation, payout, store sale, etc. |
| Status                       | Pending/confirmed/reversed as applicable              |
| Created/processed timestamps | Audit trail                                           |
| Description                  | Human-readable explanation                            |

**Rule:** never update a wallet balance without an associated ledger transaction. Balance may be cached for performance, but the ledger remains the audit source of truth.

---

## 13. Profile & Post-Registration Pending Fields

**Pending Profile Fields:** PAN Card, Aadhaar Card, Profile Photo, Bank Account Details, Passbook/Cancelled Cheque, Address.

1. After registration, show a Pending Fields / Complete Profile page.
2. The member may submit these fields **only once**; fields lock after first successful submission.
3. Any subsequent change requires a request/email workflow to Super Admin: member submits reason + requested new value → system snapshots the old value → Super Admin approves (update field, record approval) or rejects (retain original, record reason) → member is notified via the application's notification channel.

---

## 14. Daily Dynamic Company Direct Entries

Major requirement added at the end of the source. Governs company-generated placeholder members that can later become real leader identities.

### 14.1 Super Admin settings

- Daily company-direct entry count.
- Enable/disable toggle.
- Placement mode (source: entries may "always be on the right side").
- Assignment pool/status view for unassigned dummy members.

### 14.2 Daily generation logic

1. At the configured daily run time, read Super Admin configuration.
2. If disabled, create nothing.
3. If enabled, generate the configured number of dummy member records, each with a unique internal ID and placeholder/dummy name data, marked Company Direct / Unassigned.
4. Place entries per the configured placement mode (e.g. "always right" → right-side placement algorithm, §4.3).
5. **Before assignment**, a dummy entry is only a company-generated placeholder — it is **not** a member joining and does not participate in compensation.
6. Make records available in an admin assignment queue.

### 14.3 Leader assignment

When an MLM leader is ready to join, Super Admin selects an available dummy company-direct entry and enters the leader's real details into it. The dummy entry then becomes that leader's actual member identity/Customer ID. It remains a Company Direct and is treated as a normal binary-position member for all calculations from that point forward — assignment creates **no special compensation exception**. Preserve full audit history: generated date, original company ownership, assignment date, assigned leader, and operator.

**After assignment:** daily dummy-entry generation (if still enabled) continues placing new dummy entries on the Right side of that assigned leader. These entries count under the leader's Right Binary Position and participate in all applicable Binary Position, Pair/Reward, Level/Income, team-count, and other calculations exactly like normal members, subject to the normal eligibility rules for each calculation — even though their underlying identity data may remain placeholder, their Customer IDs are genuine and valid. No special exclusion applies merely because a member originated as a dummy entry.

---

## 15. Repurchase / Purchase Upline Income

Generated when a member purchases a product through a Store and the Store records the confirmed transaction. The transaction must capture member/customer, item name, item weight, rate, amount, and applicable tax/invoice information.

| Beneficiary                | Rate                                               |
| -------------------------- | -------------------------------------------------- |
| Purchasing member          | 2% of confirmed purchase/repurchase amount         |
| Direct Sponsor (Level 1)   | 1% of confirmed purchase/repurchase amount         |
| Sponsor/Direct Levels 2–6  | 0.5% each of confirmed purchase/repurchase amount  |
| Sponsor/Direct Levels 7–12 | 0.25% each of confirmed purchase/repurchase amount |

**Duplicate-beneficiary rule:** the direct Sponsor's 1% benefit is paid only once and takes precedence over any lower upline percentage — if the same person also appears deeper in the Sponsor/Direct chain, they do not receive a second, smaller payout for the same purchase. More generally, the same member never receives two earnings from one purchase for occupying more than one applicable level; only the highest applicable earning is paid.

All purchase/repurchase earnings are recorded as separate ledger transactions linked to the originating store transaction/invoice and processed idempotently, using the same centralized Laravel calculation/audit principles as every other compensation rule. All level-based beneficiaries are resolved through the Sponsor/Direct chain only.

This rule is **separate from** Store Profit Distribution (§16.4) — see §16.4 for the resolved rule on when each applies and whether both can fire on the same sale.

---

## 16. Multi-Store Management & Store Profit Distribution

### 16.1 Store Wallet, Jewellery Allocation & Payment Float

- Each Store has a dedicated **Store Wallet** used for Store-initiated member payment processing (new joinings and repurchases paid in cash at the store).
- Store creation and wallet control belong to Super Admin. Store Owner/Admin can **view** the wallet balance and permitted transactions but **cannot credit it directly**.
- **Initial allocation:** when a Store opens, Super Admin records the jewellery allocation/value assigned to it (e.g. ₹10,00,000–₹20,00,000) and may retain a company-held advance/security amount (e.g. ₹2,00,000–₹4,00,000, or another configured amount). The advance value is credited to the Store Wallet as the store's available payment balance. The jewellery allocation/stock and the Store Wallet balance are separate financial/inventory records — never substitute one for the other.
- **Top-up:** the Store Owner may pay Super Admin at any time to top up the wallet; only Super Admin confirms receipt and credits the amount. Every top-up needs an immutable reference, amount, date/time, operator, and audit record.
- **Store-initiated payment (new joining or repurchase):** the Store Admin records the payment/purchase and selects Store Wallet as the payment source. The application checks sufficient balance **before** confirming — insufficient balance blocks the transaction. On confirmation, the amount is deducted from the Store Wallet and the member payment/purchase is recorded as confirmed/eligible, per the applicable business rule. The deduction and the payment/purchase record must commit **atomically** and must never duplicate on retry.
- The Store Wallet ledger (opening/advance credit, top-ups, deductions, current balance) is complete, immutable, and auditable.

### 16.2 Sale / Transaction recording

Every store transaction (New Sale / Purchase / Repurchase) records: sale date/time, store, invoice/order reference, customer/member ID if applicable, item name, item weight, applicable rate, sale amount, GST/tax per Super Admin configuration, total invoice amount, payment source (Store Wallet where applicable) and deduction reference, and the resulting distribution status. Every confirmed purchase/repurchase generates an **invoice** that can be **printed** or **shared via WhatsApp**.

### 16.3 Store Activity & Audit Log

Super Admin must be able to view a complete activity/audit log per store, covering: which store, which Admin/Store Owner performed the action, exact date/time, action type, affected member/customer, transaction/reference ID, and relevant amount/quantity/weight/rate/status details. Applies to new joining/payment entries, purchase/repurchase entries, wallet top-ups/deductions, invoice generation and print/share actions, inventory/stock changes, store profile/settings changes, and status changes. Data changes retain old/new values. Log is filterable by store, admin/owner, date range, activity type, member/customer, reference ID, and status. This log is for Super Admin visibility; Store Admin/Owner access to the audit history is restricted per the store access boundary (§17.2).

### 16.4 Store Profit Distribution

| Beneficiary            | Rate  |
| ---------------------- | ----- |
| Store Owner            | 2%    |
| Sponsor/Direct Level 1 | 0.5%  |
| Sponsor/Direct Level 2 | 0.25% |
| Sponsor/Direct Level 3 | 0.25% |

**Store Profit Rule:** percentages are calculated on the **full amount entered as the distributable sale amount**, which is treated as already net of business costs. The three levels follow the **Sponsor/Direct chain only** — Binary Position/placement is never used.

**Applicability & co-application with §15 — (RESOLVED 12-09-2026 — user confirmation):** Store Profit Distribution (this section) applies to every store-attributed sale, which the user confirmed comes in exactly three scenarios:

1. **Walk-in / non-member direct sale** — a sale to someone who is not a GoldWave plan member. There is no member on the other side, so this sale is recorded only under the Store (no member-side entry) — Purchase/Repurchase Upline Income (§15) does **not** apply (there is no purchasing member to attribute it to), but Store Profit Distribution still fires for this store.
2. **Member jewellery purchase** — an existing member purchases jewellery through the store. This transaction is recorded **both** under the member's own Customer ID (triggering Purchase/Repurchase Upline Income, §15) **and** under the store (triggering Store Profit Distribution, this section). **Both distributions fire together on this one transaction** — they are not mutually exclusive.
3. **New joining with jewellery delivery at a store** — if a new member's joining happens at a given store and their plan jewellery is delivered through it, that sale is also recorded under that store and Store Profit Distribution fires for it (in addition to whatever registration-time compensation, e.g. Level Income, that joining separately triggers).

In all three scenarios the store receives its profit exactly per the rates/rule in this section. Do not implement store-profit logic that skips any of these three scenarios or that suppresses §15 for scenario 2.

Processing: confirm the sale → determine the distributable base → identify the store owner → resolve the 1st/2nd/3rd Sponsor/Direct levels → calculate each percentage → create separate store-profit ledger entries linked to the sale → mark distribution complete and prevent duplicate processing.

---

## 17. Admin / Store Owner — Store Operations Boundary

### 17.1 In scope for Admin/Store Owner

- Manage the assigned store's repurchases/sales, inventory/stock, transactions, and store-level reports/summaries.
- View assigned-store sales, profit, and distribution information.

### 17.2 Out of scope (Super Admin only)

- Company-wide Payment In, Payment Out, compensation, draw, members, system settings.
- Store creation, ownership assignment, company-wide store controls, Store Wallet funding/allocation.
- Full Store Activity/Audit Log visibility (Admin/Owner access is restricted to what's permitted within their own store).

### 17.3 Pending Store Decisions

Inventory management details, repurchase workflow specifics, returns policy, and POS-level details remain **PENDING CLIENT DECISION**. No store operational return/refund policy exists — the global no-refund rule (§0) applies unless the client defines a separate store return policy.

---

## 18. Status Models

| Entity                 | Suggested source-aligned statuses                                           |
| ---------------------- | --------------------------------------------------------------------------- |
| Registration           | Draft / Payment Pending / Payment Confirmed / Active / Cancelled            |
| Payment                | Pending / Paid / Failed                                                     |
| Cash Payment           | Pending Verification / Approved / Rejected                                  |
| EMI                    | Upcoming / Due / Paid / Failed / Overdue                                    |
| Payout                 | Pending / Approved / Processing / Processed / Failed / Cancelled / Rejected |
| Profile Change Request | Pending / Approved / Rejected                                               |
| Draw                   | Scheduled / Groups Generated / Executed / Reconciled                        |
| Dummy Entry            | Generated / Unassigned / Assigned / Disabled                                |
| Store Sale             | Draft / Confirmed / Cancelled                                               |
| Store Distribution     | Pending / Processed / Reversed                                              |
| Store Wallet           | Active / Suspended                                                          |

---

## 19. Background Jobs / Scheduled Processes

| Job                                  | Timing                    | Required behavior                                                                                                                                                                                                     |
| ------------------------------------ | ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Daily Company Direct Generator       | Daily; exact time TBD     | Read count/toggle, generate dummy company-direct entries, log result                                                                                                                                                  |
| Draw Group Generator                 | 15th, 12:00 AM            | Freeze eligible member population into groups                                                                                                                                                                         |
| Monthly Draw Executor                | 15th, 12:00 PM            | Sequentially draw winner per group, create prize/upline records                                                                                                                                                       |
| EMI Due Processor                    | Daily                     | Mark `upcoming`→`due` on/after the installment's due date, and `due`→`overdue` starting the very next calendar day if still unpaid (§5 item 7 — no grace period); payment itself (not this job) activates eligibility |
| Pair/Reward Monthly Evaluator (added T-007 — §7.3 required month-end evaluation, this table never listed the job for it) | Month-end | Per beneficiary with unused pair entries: consume the next unachieved milestone's required Left/Right counts (oldest first) if both the counts and the Min. Direct Members gate are met, pay the reward, repeat while more milestones are reachable in the same run (§7.2) |
| Booster Payout Processor             | Scheduled                 | Create due monthly booster benefits without duplication                                                                                                                                                               |
| Payment Webhook Processor            | Event-driven              | Verify, idempotently confirm, trigger calculations                                                                                                                                                                    |
| Store Profit Processor               | Event-driven or scheduled | Calculate/distribute confirmed sale profit                                                                                                                                                                            |
| Report Export Worker                 | On demand                 | Generate large exports asynchronously                                                                                                                                                                                 |
| Store Wallet Processor               | Event-driven              | Atomically record top-ups/advance credits and deductions; prevent duplicate processing                                                                                                                                |
| Purchase/Repurchase Income Processor | Event-driven              | Calculate 2% self / 1% direct Sponsor / 0.5%–0.25% upline earnings with duplicate-beneficiary protection                                                                                                              |

All scheduled jobs must be retry-safe and idempotent. Failures must be visible to Super Admin and must never silently duplicate financial transactions.

---

## 20. Edge Cases & Protection Rules

- Duplicate payment webhook → must not create duplicate membership/income.
- Sponsor code valid but sponsor inactive → **not defined in source; must confirm with client** before implementing behavior.
- Selected binary side occupied → continue through the selected side until the first empty position (§4.3).
- Member has more than two directs → place additional directs downline while retaining the original sponsor relation.
- Member qualifies for multiple pair milestones in one evaluation → incremental counting (§7.2) prevents reuse; evaluate every newly-reachable milestone.
- No eligible upline at draw time → winner still receives their own prize; upline benefit is simply skipped.
- Upline has exactly 10 directs → qualifies (rule is "at least 10").
- Cash payment still pending at a draw/compensation cutoff → does not count until confirmed eligible.
- Member joins on the 15th after the 12:00 AM group-generation cutoff → excluded from that day's draw cycle.
- Draw winner must be removed from subsequent draws for that group's remaining cycle.
- Daily dummy generation disabled → no new dummy records created.
- Dummy entry generated but not yet assigned → placeholder only, not treated as a member joining.
- Payout request below the configured minimum → blocked at the request step; member can never withdraw directly.

---

## 21. Resolved Decisions (from source conflicts)

The source document contained several internally inconsistent sections. The table below records the **resolved, authoritative** rule for each. Do not re-derive these from the raw `.docx` — this table is the single source of truth.

| Topic                                                             | Resolution                                                                                                                                                                                                                                                                                                   |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Plan product entitlement **(RESOLVED 12-09-2026 — spec v2.0)**    | Plans A–D (EMI): A=100gm Silver, B=100gm Silver, C=5gm Gold, D=10gm Gold (**new plan**, ₹10,000/month × 10 months). Plans E/F (one-time, renumbered from the old D/E): E=Silver choice, F=Gold choice. Every EMI plan additionally requires a Current Rate Booking vs. Future Rate Booking selection (§3.0). |
| Monthly draw prize                                                | Super Admin configures one Prize Name and Prize Value per group per draw month (§8.3); historical months retain the prize used at the time.                                                                                                                                                                  |
| Pair EMI qualification                                            | Plan A = min. 6 completed EMIs; Plans B/C = min. 2 completed EMIs; Plan D = min. **1** completed EMI (§7.3) — all Super-Admin-configurable/dynamic.                                                                                                                                                          |
| Pair reward milestone 15                                          | 1,280,000 Left/Right → ₹128,00,000 reward (§7.1 table).                                                                                                                                                                                                                                                      |
| Level Income beneficiary chain                                    | Always Sponsor/Direct chain, never Binary Position (§0, §6).                                                                                                                                                                                                                                                 |
| Daily dummy entries                                               | Remain the company's Direct relationship; after leader assignment, behave as a normal binary-position member (§14).                                                                                                                                                                                          |
| Store profit base                                                 | Full amount entered as the distributable sale amount, already net of costs (§16.4).                                                                                                                                                                                                                          |
| Store distribution levels                                         | Sponsor/Direct chain only, 3 levels (§16.4).                                                                                                                                                                                                                                                                 |
| Booster team size & duration **(updated 12-09-2026 — spec v2.0)** | Use the updated team-size thresholds and Left/Right split in the §9 table; qualify once per level, benefit runs **6** consecutive months (was 3); concurrent multi-level schedules allowed (§9.1).                                                                                                           |
| Cash payment activation                                           | Inactive until Super Admin confirms receipt and activates; activating operator recorded (§10.2).                                                                                                                                                                                                             |
| Pending profile fields                                            | PAN, Aadhaar, Profile Photo, Bank Account Details, Passbook/Cancelled Cheque, Address — one-time entry, later changes need Super Admin request (§13).                                                                                                                                                        |
| Placement traversal on occupied side                              | Straight down the selected side until first empty position (§4.3).                                                                                                                                                                                                                                           |
| Payout controls                                                   | Member can only request, never self-withdraw; default min. ₹500 (configurable); Super Admin pays via Cheque/GPay/UPI/Bank Transfer/in-app provider, individually or in bulk/batch; bank details manually verified; TDS configurable, default 0%; optional withdrawal fee configurable, default 0 (§11).      |
| Draw eligibility                                                  | Full payment for one-time plans (E/F); for EMI plans (A–D) a **separate rule from Pair/Reward qualification** (§8.7): 6 EMIs for Plan A, 2 EMIs for Plan B/C, 1 EMI for Plan D.                                                                                                                              |
| Store Wallet                                                      | Dedicated per-store wallet funded by allocation advance + Super Admin-confirmed top-ups; used only for store-initiated new joining/repurchase payments; atomic, auditable deductions (§16.1).                                                                                                                |
| Purchase/Repurchase Upline Income                                 | 2% self / 1% direct Sponsor / 0.5% (L2–6) / 0.25% (L7–12), with duplicate-beneficiary protection (§15).                                                                                                                                                                                                      |
| Purchase/Repurchase invoice                                       | Every confirmed transaction generates an invoice, printable and shareable via WhatsApp (§16.2).                                                                                                                                                                                                              |
| GST & TDS settings                                                | Centrally configured by Super Admin, applied consistently, with effective-history preserved.                                                                                                                                                                                                                 |

### Still open — CLIENT CONFIRMATION REQUIRED

| Topic                                                      | Why it's open                              |
| ---------------------------------------------------------- | ------------------------------------------ |
| Store inventory, repurchase workflow, returns, POS details | Explicitly deferred to the client (§17.3). |

### Resolved via `GoldWave_Claude_instructions_2.docx` (client spec v2.0, compared 12-09-2026)

Comparing the client's updated `GoldWave_Claude_instructions_2.docx` against the previously-consolidated `GoldWave_Claude_instructions.docx` surfaced a real scope change (not just a clarification): a new Plan D, a dual Current/Future Rate Booking mechanism for all EMI plans, an EMI-based Draw-eligibility rule separate from Pair/Reward qualification, and a Booster duration/table change. No implementation code exists yet for any of this (T-003/T-005/T-007/T-010/T-011 are all still Pending) — this is a documentation-only update; **do not start coding any of it without the user's explicit go-ahead**, per the standing instruction recorded in `Docs/PROGRESS.md`.

| Topic                                                                                                                                                                                                                   | Resolution                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | Updated in |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| Plan product entitlement (was CLIENT CONFIRMATION REQUIRED)                                                                                                                                                             | Finalized A–F plan table with exact jewellery weights; see §3.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | §3         |
| Rate Booking mechanism (new)                                                                                                                                                                                            | Every EMI plan requires a mandatory Current Rate Booking vs. Future Rate Booking selection at registration, each with its own formula.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | §3.0       |
| Draw eligibility for EMI plans (new, separate from Pair/Reward)                                                                                                                                                         | Plan A=6, B=2, C=2, D=1 completed EMIs required for Draw eligibility — a distinct rule from §7.3's Pair/Reward EMI counts.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | §8.7       |
| Booster duration & table (was 3 months)                                                                                                                                                                                 | Now 6 consecutive months per level; table adds Min. Directs, binary Team size, and Left/Right split columns.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | §9         |
| Possible copy-paste artifact in spec v2.0 §31.3 Financial Integrity (a bullet there reads "Booster Direct/team qualification Show 6-month schedule" instead of the expected "Store calculation snapshots..." principle) | **RESOLVED 12-09-2026 — user confirmation:** confirmed to be a mistake in the client's docx, not an intentional rule change. The original "Store calculation snapshots so historical reports remain reproducible even if settings change later" principle stays in §22 as-is. **Standing note for whoever implements Income Booster (T-011) or any Financial Integrity/reporting-snapshot work (§22):** re-check this specific spot in the source document when writing that code — if any real conflict or contradiction turns up between the Booster rules and the reproducibility principle at that point, flag it and discuss before proceeding, rather than assuming this resolution still covers it. | §9, §22    |

### Resolved via client/user confirmation (12-09-2026)

The items below were open and are now resolved. The user provided these answers directly (on behalf of/from the client); the normative sections listed have already been updated to state the resolved rule — this log preserves why each was open and exactly what was decided, per the project's zero-rework/no-invented-assumptions discipline.

| Topic                                                                                                                          | Resolution                                                                                                                                                                                                                                                                                                                                                                                                | Updated in |
| ------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| Store Profit Distribution (§16.4) vs. Purchase/Repurchase Upline Income (§15) — do both apply to the same transaction?         | Store-attributed sales come in exactly 3 scenarios: (1) walk-in/non-member sale — Store Profit Distribution only; (2) member jewellery purchase — **both** Purchase/Repurchase Upline Income and Store Profit Distribution fire on the same transaction; (3) new joining with jewellery delivered at a store — Store Profit Distribution applies to that store sale too.                                  | §16.4      |
| Store Owner beneficiary identity — is a Store Owner also a network Member with a wallet?                                       | Yes — a Store Owner is also a full GoldWave network Member (own Customer ID, sponsor/placement, wallet); their 2% Store Profit Distribution share is credited to that Member wallet.                                                                                                                                                                                                                      | §2         |
| Pair/Reward milestone-wise minimum Direct Members — no concrete numbers were given.                                            | Flat default of **2** Direct Members for every one of the 15 milestones (not a progression), Super-Admin-configurable/dynamic.                                                                                                                                                                                                                                                                            | §7.3       |
| Plan D (₹10,000/10 months) Pair/Reward completed-EMI count — spec v2.0 gave no default, deferred to Super Admin configuration. | Default of **1** completed EMI, Super-Admin-configurable/dynamic (same pattern as Plans A/B/C — a real default is seeded, not left empty).                                                                                                                                                                                                                                                                | §7.3       |
| Income Booster — can a member hold concurrent 3-month schedules across levels?                                                 | Yes — a member can hold concurrent schedules across all three levels simultaneously if they separately qualify for each; each level's schedule and qualification is tracked independently. _(The schedule length was 3 months when this question was asked; client spec v2.0, compared 12-09-2026, separately changed the duration to 6 months — the concurrency answer itself is unaffected, see §9.1.)_ | §9.1       |
| Sponsor inactive at registration time — is registration under an inactive sponsor allowed?                                     | **No.** If the sponsor's own account is not currently Active — for any reason (own pending payment, Super Admin suspension, anything else) — their invite/sponsor code is treated as invalid and registration under it is blocked, same as an invalid code. Re-checked at the moment of registration every time; a sponsor who becomes Active again can sponsor new members from then on.                 | §2.1       |

Do not re-open these without a new client-confirmed change — treat the resolutions above as final business rules, not as still-open items.

### Resolved via user confirmation (13-09-2026 — pre-coding pass for T-005)

| Topic                                                                                                                                                | Resolution                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | Updated in                 |
| ---------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| EMI installment due-date cadence — no source spec ever defined which day of the month installments fall on.                                          | Activation-date anniversary: installment 1 due on the activation date itself, each later installment due the same day-of-month one calendar month on. Schedule is fixed at generation time and never drifts based on actual payment dates.                                                                                                                                                                                                                                                                                                     | §5 item 7                  |
| EMI overdue grace period — no source spec defined how long after the due date a `due` installment becomes `overdue`.                                 | No grace period — becomes `overdue` the very next calendar day after the due date if still unpaid.                                                                                                                                                                                                                                                                                                                                                                                                                                             | §5 item 7, §19             |
| Draw-eligibility completed-EMI thresholds (§8.7) — `DATABASE_SCHEMA.md` had flagged this as having "no schema home yet".                             | Resolved as an architecture decision, not a business-rule question: these thresholds are Super-Admin-configurable exactly like the Pair/Reward EMI thresholds (§7.4), so they live in the existing `rule_values` store under their own key — no new table/column. A member's completed-installment count is computed on demand (`COUNT` of `emi_installments` with `status = paid`), not tracked via a denormalized counter, consistent with the project's "status columns are the only lifecycle mechanism" principle (`DATABASE_SCHEMA.md`). | §8.7, `DATABASE_SCHEMA.md` |
| EMI installment payment ordering — no source spec said whether a member may pay ahead of schedule (advance/prepay) or only the next-due installment. | A member may only pay their schedule's single earliest-unpaid installment at a time — no skip-ahead, no advance/prepayment of a later `upcoming` installment even if the current one is settled first. Matches the strictly sequential worked examples in `TEST.md` scenario 8.                                                                                                                                                                                                                                                                | §5 item 8, §10             |

Do not re-open these without a new client-confirmed change — treat the resolutions above as final, same as the rest of this section.

### Resolved via pre-coding pass for T-006 (13-09-2026)

| Topic                                                                                                                              | Resolution                                                                                                                                                                                                                                                                                                                                                                                        | Updated in                 |
| ------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| §6.1 point 9 "upline inactive" — no source spec defined what makes an upline beneficiary ineligible.                                | Not a new business rule: reuses the existing `members.status === 'active'` definition already settled for sponsor-inactive-at-registration (§21, resolved 12-09-2026). A beneficiary whose status is anything other than `active` (e.g. `cancelled`) at calculation time is skipped with `skip_reason = upline_inactive`, still recording who was skipped.                                      | §6.1                       |
| `income_ledger_calculations.beneficiary_member_id` was NOT NULL in T-002's schema, but §6.1 point 9 / `Docs/TEST.md` scenario 1's edge case require a `skipped` row even when the chain is too short to reach a level at all (no member exists there to attach). | Schema gap, not a rule change: relaxed to nullable in a new migration (`adjust_income_ledger_calculations_for_level_income`), `skip_reason = chain_too_short` for this case vs. `upline_inactive` for a real-but-ineligible beneficiary. Also added a DB-level `(source_payment_id, level_no)` unique constraint per ARCHITECTURE.md's idempotency principle.                                    | `DATABASE_SCHEMA.md`, §21  |
| `WalletLedgerService` is nominally T-008's deliverable, but ARCHITECTURE.md's Compensation Engine rule 3 requires every compensation Action (including T-006's `CalculateLevelIncome`) to credit through it.                                                     | Architecture decision, not a business-rule question: front-loaded a minimal credit-only `WalletLedgerService` in T-006, the same precedent already used for `SponsorChainResolver` (introduced T-004 ahead of T-006 consuming it) and `RuleVersionService` (introduced T-003). T-008 extends it with debit()/hold logic for Payout without needing to touch T-006/T-007's calling code — no rework. | `ARCHITECTURE.md`          |

Do not re-open these without a new client-confirmed change — treat the resolutions above as final, same as the rest of this section.

### Resolved via pre-coding pass for T-007 (13-09-2026)

| Topic                                                                                                                              | Resolution                                                                                                                                                                                                                                                                                                                                                                                        | Updated in                 |
| ------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| §7 never states whose account gets credited when a new eligible joining occurs — unlike Level Income's explicit 12-level Sponsor chain walk, no beneficiary-chain rule was written for Pair/Reward at all. | **User confirmation:** a team-size count, not direct-to-direct — every ancestor in the member's entire Binary Position chain (unbounded depth) gets one pair-eligible entry on whichever leg the joining falls under, mirroring Booster's (§9) full-subtree semantics. See the new "Pair/Reward Beneficiary Chain Rule" callout in §7 for the full resolution and why an immediate-parent-only reading was rejected (makes milestone 2+ unreachable). | §7                         |

Do not re-open these without a new client-confirmed change — treat the resolutions above as final, same as the rest of this section.

### Resolved via pre-coding pass for T-008 (13-09-2026)

| Topic                                                                                                                              | Resolution                                                                                                                                                                                                                                                                                                                                                                                        | Updated in                 |
| ------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| A tension in the schema/docs, not a business-rule gap: `DATABASE_SCHEMA.md`'s general "financial tables are append-only ledgers, rows never updated" rule vs. `wallet_ledger_entries` having its own `status` (pending/confirmed/reversed) and nullable `processed_at` columns, which only make sense if a single row's status is expected to transition over time (needed for §11.1's Wallet Hold: reserve now, finalize or release later). | **Architecture decision (schema-evidence-based, not guessed):** a hold's own pending→confirmed/reversed transition updates that same row (setting `processed_at`) rather than creating a second row — this is the transaction's own declared lifecycle completing, not a correction of a mistake. §22's "never delete financial history, reverse/correct through linked transactions only" is about fixing an error after the fact (a genuinely separate concern) and doesn't forbid this. `payout_requests.hold_ledger_entry_id` (T-002 schema) already expects exactly one row per hold, reinforcing this reading. | `ARCHITECTURE.md`, `WalletLedgerService` |

Do not re-open these without a new client-confirmed change — treat the resolutions above as final, same as the rest of this section.

**Clarification, not an open item (resolved by re-reading the tree structure, 10-09-2026):** the Purchase/Repurchase "duplicate beneficiary" rule (§15) describes a direct Sponsor being paid only once even if they "also appear later in the Sponsor/Direct chain." Structurally, a Sponsor/Direct chain is a simple ancestor path (each member has exactly one sponsor), so the same person cannot occupy two different levels of one member's chain — the scenario the source is guarding against cannot actually arise once `SponsorChainResolver` (`Docs/ARCHITECTURE.md`) walks a real `sponsor_id` chain. Implement the guard anyway (track paid beneficiary IDs while walking the chain, skip if already paid) as a defensive check — it should simply never trigger in normal operation, and if it ever does trigger, that is itself a signal of corrupted sponsor-chain data worth alerting on, not a normal business event.

Do not invent behavior for anything in this "Still open" table — surface it to the user/client instead of assuming an answer.

---

## 22. Financial Integrity Principles (implementation-wide)

- Use immutable source-event references for all financial calculations.
- Wrap membership activation and compensation creation in database transactions.
- Use idempotency keys for gateway callbacks and every scheduled job.
- Never delete financial history — reverse/correct through linked transactions only.
- Store calculation snapshots (rule/config version) so historical reports stay reproducible even after settings change later.

## Lifecycle States

See §18 (Status Models) above — this is the canonical lifecycle-state list for every domain entity.

## Change Rules

- This file contains business meaning, not framework or UI implementation detail — page layout and field-level UI content live in `INSTRUCTIONS.md`; conceptual entity/relationship shape lives in `DATABASE_SCHEMA.md`.
- Update it whenever a non-obvious validation, calculation, lifecycle, entitlement, or policy changes — and update `AGENTS.md`'s Project Card / this file's Status block together.
- Do not duplicate API field definitions or table layouts; link to their canonical documents.
- Any new client clarification that changes a rule above must update the relevant section directly (don't just append a note) so this file never accumulates contradictory statements the way the original `.docx` did.
