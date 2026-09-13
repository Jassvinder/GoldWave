# Feature Requirements

## Status

- Status: Active
- Last verified: 09-09-2026
- Requirement owner / evidence: Client specification `GoldWave_Claude_instructions.docx` (v2.0), fully migrated into this file and `Docs/DOMAIN_LOGIC.md`. Treat the `.docx` as archival only.

This file is the **page-level** functional spec: what each screen shows and what it must do. Business rules behind these pages (calculations, eligibility, statuses) are documented once in `DOMAIN_LOGIC.md` and linked from here — do not duplicate the rule text in this file.

Estimated UI size: **~40 screens** (Public/Auth 6, Member 18, Admin/Store Owner 6, Super Admin 10), after consolidating Store Owner/Admin operations and moving company-wide controls to Super Admin. Some can be tabs/drawers/modals instead of separate routes — final route count is an implementation choice.

| Area                  | Pages / screens | Notes                                                                                                                 |
| --------------------- | --------------- | --------------------------------------------------------------------------------------------------------------------- |
| Public/Auth           | 6               | Login, registration, payment confirmation, password/account recovery                                                  |
| Member                | 18              | Dashboard, profile, plans, payments, network, income, draw, booster, wallet, reports, support/requests                |
| Admin / Store Owner   | 6               | Assigned-store dashboard, store profile/settings, repurchases/sales, inventory, transactions, store reports           |
| Super Admin / Company | 10              | Company-wide controls, members/network, dummy/direct-entry engine, draw, rates, payouts, stores, audit, configuration |
| System/background     | Not UI pages    | Schedulers, queue jobs, calculation/audit processes — see `DOMAIN_LOGIC.md` §19                                       |

---

## Member Portal — Page Inventory

| #   | Page                     | Main content / actions                                                                              |
| --- | ------------------------ | --------------------------------------------------------------------------------------------------- |
| M01 | Dashboard                | Profile summary, plan, payment/EMI status, income summary, team counts, draw/booster status, alerts |
| M02 | My Profile               | Personal data, Customer ID, account status, locked/one-time fields                                  |
| M03 | Complete Pending Profile | One-time entry of pending fields (see §"Complete Profile" below)                                    |
| M04 | Change Request           | Submit correction/change request after fields are locked                                            |
| M05 | Membership Plan          | Current plan, product benefit, plan details                                                         |
| M06 | EMI Schedule             | Installments, due/paid status, pay action                                                           |
| M07 | Payment History          | Payment In transactions                                                                             |
| M08 | Directs View             | Personal sponsor/direct network — see §"Directs View" below                                         |
| M09 | Tree View                | Binary placement tree — see §"Tree View" below                                                      |
| M10 | Level Income             | 12-level income history + detail; Sponsor/Direct chain levels                                       |
| M11 | Pair/Reward              | Progress, milestones, consumed/available business, rewards                                          |
| M12 | Income Booster           | Qualification/progress/3-month benefit schedule                                                     |
| M13 | Monthly Draw             | Groups, search, winners, own draw status                                                            |
| M14 | Wallet                   | Balance + transaction ledger                                                                        |
| M15 | Payout Request           | Withdrawable balance, configurable minimum, payout request and status                               |
| M16 | Payout History           | Payout request/status, payment mode, references and payment history                                 |
| M17 | Reports                  | Own downloadable reports                                                                            |
| M18 | Notifications/Support    | System notifications + request/status tracking                                                      |

## Admin / Store Owner Portal — Page Inventory

_(Also referred to as "Store Operations" — merged into one role; no separate Store portal exists.)_

| #   | Page                     | Main content / actions                                                                                                                  |
| --- | ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------- |
| A01 | Store Dashboard          | Assigned store sales, repurchases, inventory status, owner share, distributions, alerts                                                 |
| A02 | Store Profile & Settings | Assigned store details and permitted store-level settings                                                                               |
| A03 | Repurchases / Sales      | Manage new joining payments and store repurchases/sales; use Store Wallet as payment source; generate invoices; view transaction status |
| A04 | Inventory                | Products, stock, stock movements, inventory status                                                                                      |
| A05 | Store Transactions       | Transaction details, invoice/reference, member, item/weight/rate/amount, Store Wallet deduction, status, operational history            |
| A06 | Store Reports            | Assigned-store sales, inventory, profit, and distribution reports                                                                       |

## Super Admin / Company Control Pages

| #   | Page                        | Main content / actions                                                                                    |
| --- | --------------------------- | --------------------------------------------------------------------------------------------------------- |
| S01 | System Dashboard            | Global health, business controls, queues, exceptions                                                      |
| S02 | Admin Users & Permissions   | Create/manage Admin users and their access boundaries                                                     |
| S03 | Compensation Rule Versions  | Approve/publish rule versions and effective dates                                                         |
| S04 | Daily Dummy Entry Settings  | Daily count, enable/disable, placement mode, generation controls                                          |
| S05 | Dummy Entry Assignment      | Enter leader details into an available dummy entry; converts it into the leader's member identity         |
| S06 | Draw Master Settings        | Group size, monthly prize name/value, draw configuration/history                                          |
| S07 | Gold & Silver Rate Settings | Gold and Silver rates together on one page, with effective-date history                                   |
| S08 | Payout & TDS Settings       | Minimum withdrawal, payout schedule/controls, TDS percentage, payout provider, payout processing settings |
| S09 | Store Management            | Create/manage multiple stores, assign Store Owners, record jewellery allocation/advance and store status  |
| S10 | Store Wallet Management     | View/credit Store Wallets, record Super Admin top-ups, advance balance, wallet transaction history        |

Super Admin also gets: full Admin Dashboard (see below), Admin Member Management, Admin Compensation Management, Admin Draw Management, and Reports — these are described as their own sections below because they are functionally distinct screens, not because they belong to a separate role.

---

## Registration & Onboarding (Public/Auth)

### Registration page

- Invitation/Sponsor Code input; validated sponsor name displayed directly under the code input.
- Left / Right placement selection.
- Mobile number, email, name.
- Plan cards for ₹1,000×20, ₹3,000×10, ₹5,000×10, ₹20,000, and ₹50,000 (see `DOMAIN_LOGIC.md` §3 for plan details).
- Order/payment summary.
- Payment mode: Online or Cash (cash registrations stay inactive until Super Admin confirms and activates — `DOMAIN_LOGIC.md` §3.1, §10.2).
- Terms/consent area (if the company later requires it).
- Continue to payment / submit registration action.

Business logic for this page: `DOMAIN_LOGIC.md` §3.1 (validation, placement, activation), §4.3 (binary placement algorithm).

### Complete Pending Profile (M03)

Shows the Pending Fields form (PAN Card, Aadhaar Card, Profile Photo, Bank Account Details, Passbook/Cancelled Cheque, Address). Submittable once; locks after first submission. See `DOMAIN_LOGIC.md` §13.

### Change Request (M04)

Lets the member submit a correction request (reason + new value) for a locked field, and shows request status with old/new value. Approval workflow: `DOMAIN_LOGIC.md` §13.

---

## Member EMI Page (M06)

- Selected plan and total commitment.
- Installment number, due month/date, amount, status.
- Paid date, payment reference, payment mode.
- Pending/paid/failed indicators.
- Pay installment action for online payment.
- Cash payment request/record where enabled.
- Eligibility indicator for compensation rules.

Business logic: `DOMAIN_LOGIC.md` §5, §6, §7.3.

**Backend + minimal working page shipped in T-005** (`resources/js/pages/member/emi.tsx`, `Member\EmiController`): full schedule listing, status badges, and the Online/Cash Pay action for the single next-due installment (§5 item 8 — no skip-ahead). Not yet built here: paid date/payment reference/mode columns, and the "Eligibility indicator for compensation rules" (depends on T-006/T-007's Level Income/Pair eligibility, which don't exist yet) — those + final visual design are T-015's job, on top of this same backend.

---

## Directs View (M08)

- Header always shows the **logged-in member's** Name and Customer ID; stays constant through all navigation.
- Below the header: logged-in member's personal direct members, rendered as a tree diagram — the Selected Member card at top, one vertical connector down to a horizontal trunk, one vertical connector from the trunk into each direct's card. When there are more directs than fit one row, they wrap into additional rows, each row connected to the one above by a stub dropping from that row's own center (not a fixed display cap — every direct remains reachable).
- Clicking a Direct Member card navigates to that member's own Directs View (a real page visit, so it lands in browser history) — recursive, to any depth.
- **Back button** returns to the previous view via browser history — this retraces whatever path (clicks or search) the viewer actually took.
- **Search box**: look up any Customer ID; resolves only if that Customer ID is within the viewer's own downline (same rule as recursive navigation — a Customer ID on a different, unrelated leg is rejected exactly like an unknown one, never revealed as "exists but not visible"). Super Admin's search is unrestricted.
- Sponsor/Direct relationships only — never Binary Position.
- Super Admin can open this view for any member.

Full behavioral rule: `DOMAIN_LOGIC.md` §4.1. (Back button and search added 13-09-2026, live UI iteration — not in the original client spec, decided directly with the user; see `Docs/TASKS.md` T-004.)

## Tree View (M09)

- Header always shows the logged-in member's Name and Customer ID; stays constant through navigation.
- Logged-in member starts as Root; Left/Right placement branches shown below, connected by the same trunk-line pattern as Directs View (vertical stub from parent → horizontal line spanning Left/Right centers → vertical stub into each child/Empty slot).
- Clicking any node makes it the Selected Member; that member becomes the new Root with its own branches (recursive) — implemented as a full re-root page visit rather than a separate in-place-expand interaction (see `TreeController`'s docblock for why one interaction satisfies both "becomes the new Root" and "each child can be expanded").
- Zoom (+/−/Reset buttons), pan (click-drag) supported on the diagram.
- **Back button** and **Customer ID search** — same behavior and downline-only restriction as Directs View above.
- Node cards show Customer ID, Name, Status.
- **Rectangular cards only — no circular node designs anywhere in the application.**
- Binary Position/Placement only — never Sponsor/Direct.
- Super Admin can open this view for any member.

Full behavioral rule: `DOMAIN_LOGIC.md` §4.2. (Back button and search added 13-09-2026, live UI iteration — see `Docs/TASKS.md` T-004.)

---

## Level Income (M10)

12-level income history and detail view, showing the source payment and amount per level. Calculation rule: `DOMAIN_LOGIC.md` §6.

## Pair/Reward (M11)

Progress toward the next milestone, milestones table, consumed vs. available business (Left/Right), reward history. Rule: `DOMAIN_LOGIC.md` §7.

## Income Booster (M12)

Current direct count, current total team size, qualification status per booster level, start date, active month number, monthly benefit, paid/remaining benefit history, qualification progress. Rule: `DOMAIN_LOGIC.md` §9.

## Monthly Draw (M13) / Admin Draw Management

Member view: group dropdown/list, selected group's member list, search by Customer ID, columns (Sr. No., Customer ID, Name), draw status and winner display, winner history for previous draws, slot-machine animation on live result.

Admin view: draw cycle/date, group size configuration, generated groups, eligible member count per group, winner, prize item/value, upline benefit result, execution status/timestamps, manual review/reconciliation screen.

Rule: `DOMAIN_LOGIC.md` §8.

## Wallet (M14)

Balance + full transaction ledger (see `DOMAIN_LOGIC.md` §12 for ledger fields).

## Payout Request (M15) / Payout History (M16)

- Available balance, withdrawable/eligible balance, withdrawal amount input.
- Minimum payout request enforcement (default ₹500, Super Admin configurable).
- Monthly payout history: amount, mode, reference, date, status (Pending / Processed / Failed / Cancelled).
- Bank/beneficiary information where required.

Rule: `DOMAIN_LOGIC.md` §11.

## Reports (M17)

Own downloadable reports — see the "Reports" section below for the full report catalog.

## Notifications/Support (M18)

System notifications plus request/status tracking (profile change requests, cash payment status, etc.).

---

## Admin Dashboard — What Appears

- Total members and active/pending members.
- New registrations / today's entries.
- Payment In summary: paid, pending, failed.
- EMI due/overdue summary.
- Level/Pair/Booster income generated.
- Payout pending/processed summary.
- Upcoming/last draw status.
- Daily company-direct dummy entry status.
- Store sales/profit/distribution summary.
- Alerts for cash payments, profile change requests, large-value payouts.

### Admin quick actions

Add/manage member (where permitted), review cash payment, review profile-change requests, process payout, open draw management, manage products/rates/GST/TDS settings, manage stores/allocations/Store Wallets, open reports, open compensation/audit logs.

---

## Admin Member Management

### Member list

Customer ID, name, mobile/email, plan, sponsor, placement, status, join date. Search by Customer ID/name/mobile. Filter by plan/status/date/sponsor. Pagination/export.

### Member detail

Profile and one-time field state; membership plan and product benefit; sponsor/direct relation; placement parent/side; direct count and team size; EMI schedule/payment history; income history; wallet/ledger; payout history; draw history; booster history; store-profit history if applicable; audit/activity history.

Security: sensitive financial/profile changes require appropriate authorization and create audit records (`SECURITY.md`).

---

## Admin Compensation Management

### Configuration page

Level percentages L1–L12; pair value per eligible joining; reward milestone thresholds/rewards; pair qualification requirements; booster thresholds/benefits/duration; effective date/version of each configuration.

### Calculation audit page

Source event/payment; member/Sponsor chain beneficiary; rule version; level/rate; calculated amount; eligibility status/reason; created timestamp; reversal/reference if applicable.

Best practice: compensation rules should be versioned, and historical transactions stay tied to the rule version active when they were calculated (`DOMAIN_LOGIC.md` §22).

---

## Store Pages (A01–A06 / S09–S10)

### Store list / management page (Super Admin)

Store ID/name, owner, contact/location/status, total sales, profit eligible for distribution, store status active/inactive, actions (view/edit/sales/reports), Store Wallet balance, jewellery allocation/value, advance amount credited to Store Wallet.

### Store detail page

Store profile, owner details, sales summary, profit summary, distribution summary, recent sales/transactions, beneficiary/upline distribution history, jewellery allocation/value and advance, Store Wallet balance and wallet ledger, top-up history, Store Wallet payment deductions.

### Sale entry / transaction page

Sale date/time, store, invoice/order/reference, customer/member if applicable, sale amount, cost/expense if used, calculated profit, distribution status, transaction type (New Sale / Purchase / Repurchase), Customer/Member ID, item name, item weight, applicable rate, GST/tax amount per Super Admin configuration, total invoice amount, payment source (Store Wallet where applicable), Store Wallet deduction reference, invoice actions (Print, Share via WhatsApp).

Business logic for store profit distribution and Store Wallet: `DOMAIN_LOGIC.md` §16.

---

## Reports

| Report             | Key filters / columns                                        |
| ------------------ | ------------------------------------------------------------ |
| Membership         | Customer ID, plan, sponsor, placement, status, join date     |
| EMI                | Plan, installment no., due date, paid date, status, member   |
| Level Income       | Source payment, member, level, rate, amount, status          |
| Pair/Reward        | Milestone, left/right counts, consumed entries, reward, date |
| Draw               | Cycle, group, member count, winner, prize, upline benefit    |
| Booster            | Level, qualification, month, amount, paid status             |
| Payment In         | Mode, reference, amount, status, date                        |
| Payment Out        | Recipient, amount, mode, reference, status, date             |
| Wallet/Ledger      | Credit/debit, category, source, amount, balance              |
| Store Sales        | Store, sale, profit base, distribution, date                 |
| Store Distribution | Owner/L1/L2/L3, rate, amount, source sale                    |

CSV/Excel/PDF export for operational reports; exports respect role permissions and filters; large exports are queued rather than blocking the web request (`PERFORMANCE_GUIDE.md`).

---

## Page-Level Acceptance Checklist — Member Portal

| Page                  | Must be visible                                                                        | Must work                                                                                             |
| --------------------- | -------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Dashboard             | Plan, payment/EMI, income, team/direct counts, draw/booster                            | All summary values link to source modules                                                             |
| Profile               | Customer ID, profile, lock state                                                       | One-time edit then locked                                                                             |
| Complete Profile      | Pending fields                                                                         | One successful submission only                                                                        |
| Change Request        | Old/new value, reason, status                                                          | Submit and track request                                                                              |
| Membership            | Plan + product benefit                                                                 | View current plan/entitlement                                                                         |
| EMI                   | All installments                                                                       | Pay and see confirmed status                                                                          |
| Payment History       | All payment records                                                                    | Filter/detail/reference                                                                               |
| Directs               | Personal directs only                                                                  | Navigate to direct/member detail                                                                      |
| Tree                  | Binary placement                                                                       | Expand/zoom/pan/search                                                                                |
| Level Income          | L1–L12 entries                                                                         | Show source payment and amount                                                                        |
| Pair                  | Left/right progress + milestones                                                       | Show unused/consumed concept                                                                          |
| Booster               | Direct/team qualification                                                              | Show 3-month schedule                                                                                 |
| Draw                  | Groups/search/winners                                                                  | Show own draw and group results                                                                       |
| Wallet                | Balance + ledger                                                                       | Every balance change traceable                                                                        |
| Payout                | Withdrawable balance, configurable minimum, verified bank details, TDS, payout methods | Submit eligible withdrawal request; track status/history; support bank transfer and Cheque processing |
| Reports               | Own reports                                                                            | Export/download                                                                                       |
| Notifications/Support | Requests/notices                                                                       | Status tracking                                                                                       |

## Page-Level Acceptance Checklist — Admin / Super Admin

| Area                | Acceptance points                                                                                                                       |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Members             | Search by GWL ID; profile; sponsor; placement; plan; status; financial history                                                          |
| Payments            | Online verification; cash approval; failed visibility                                                                                   |
| EMI                 | Schedule and actual payment status; overdue reporting                                                                                   |
| Products/Rates      | Configure item/rate references with effective dates                                                                                     |
| Compensation        | View/configure rules; calculation audit; no duplicate credits                                                                           |
| Pair                | Milestone progress; consumed entries; qualification                                                                                     |
| Draw                | Group generation; search; sequential winner execution; upline benefit                                                                   |
| Booster             | Qualification and scheduled 3-month benefits                                                                                            |
| Payout              | Payout request queue; configurable minimum; Cheque, GPay/UPI, Bank Transfer/in-app payout; bulk/batch payouts; references and audit     |
| Wallet              | Full ledger/reconciliation                                                                                                              |
| Profile Requests    | Approve/reject with old/new snapshots                                                                                                   |
| Dummy Entries       | Daily settings, generated queue, assignment and audit                                                                                   |
| Stores              | Multiple stores, owner, sales, profit and distributions                                                                                 |
| Reports             | Filter/export across all financial/business modules                                                                                     |
| System Jobs         | Run status, failures, retry visibility                                                                                                  |
| Store Wallet        | Store allocation/advance; Super Admin top-ups; Admin payment deductions; balance and immutable ledger                                   |
| Purchase/Repurchase | Member ID; item/weight/rate/amount; GST; invoice; Store Wallet payment; 2%/1%/0.5%/0.25% distribution; duplicate-beneficiary protection |
| Invoice             | Generate for confirmed purchase/repurchase; print and WhatsApp share                                                                    |
| Store Activity Log  | Super Admin can review complete store activity: store, operator, timestamp, action, affected record, old/new values                     |

## Writing Rule

Describe observable behavior and decisions, not a step-by-step implementation. Keep each rule in its canonical document and link across documents — business rules and calculations belong in `DOMAIN_LOGIC.md`; conceptual data relationships belong in `DATABASE_SCHEMA.md`; end-to-end flows belong in `FLOWCHART.md`.
