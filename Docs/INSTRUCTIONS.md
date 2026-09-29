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

| #   | Page                          | Main content / actions                                                                                                                                                                                                                                                                                   |
| --- | ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| M01 | Dashboard                     | Profile summary, plan, payment/EMI status, income summary, team counts, draw/booster status, alerts                                                                                                                                                                                                      |
| M02 | My Profile                    | Personal data, Customer ID, account status, locked/one-time fields                                                                                                                                                                                                                                       |
| M03 | Complete Pending Profile      | One-time entry of pending fields (see §"Complete Profile" below)                                                                                                                                                                                                                                         |
| M04 | Change Request                | Submit correction/change request after fields are locked                                                                                                                                                                                                                                                 |
| M05 | Membership Plan               | Current plan, product benefit, plan details. **T-175 (29-09-2026):** the "Book at Current Rate" popup shows today's rate and the total value, but no metal value or making line; the total includes making. **T-167 (28-09-2026):** the "Book at Current Rate" popup shows maintenance as "first month ₹x · ₹y in total" and the new EMI as "₹first first → ₹last last, reduces every month". The Rate card for a Current Rate member shows "EMI (reduces every month): ₹next next → ₹last last". The EMI Schedule page describes Current Rate as EMIs "each a little less than the one before" (`DOMAIN_LOGIC.md` §3.0). The Plan Code (A–F letter) is no longer shown here (T-154 rule). **T-166 (28-09-2026):** the button is **Request Booking at Current Rate**. Its popup is an estimate at today's rate and says the rate and EMIs are fixed on the day Super Admin approves; the button in it reads **Send request**. While a request is pending, the Rate card shows an amber "Current Rate booking requested — sent on DD-MM-YYYY…" notice instead of the button. The EMI Schedule page (M06) shows the same pending notice, or after a cancel a red "cancelled on DD-MM-YYYY" box with Super Admin's message and "You can request again from the Membership Plan page" |
| M06 | EMI Schedule                  | Installments, due/paid status, pay action                                                                                                                                                                                                                                                                |
| M07 | Payment History               | Payment In transactions                                                                                                                                                                                                                                                                                  |
| M08 | Directs View                  | Personal sponsor/direct network — see §"Directs View" below                                                                                                                                                                                                                                              |
| M09 | Tree View                     | Binary placement tree — see §"Tree View" below                                                                                                                                                                                                                                                           |
| M10 | Level Income                  | 12-level income history + detail; Sponsor/Direct chain levels                                                                                                                                                                                                                                            |
| M11 | Pair/Reward                   | Progress, milestones, consumed/available business, rewards                                                                                                                                                                                                                                               |
| M12 | Income Booster                | Qualification/progress/3-month benefit schedule                                                                                                                                                                                                                                                          |
| M13 | Monthly Draw                  | Groups, search, winners, own draw status                                                                                                                                                                                                                                                                 |
| M14 | Wallet                        | Balance + transaction ledger                                                                                                                                                                                                                                                                             |
| M15 | Payout Request                | Withdrawable balance, configurable minimum, payout request and status                                                                                                                                                                                                                                    |
| M16 | Payout History                | Payout request/status, payment mode, references and payment history                                                                                                                                                                                                                                      |
| M17 | Reports                       | Own downloadable reports                                                                                                                                                                                                                                                                                 |
| M18 | Notifications/Support         | System notifications + request/status tracking                                                                                                                                                                                                                                                           |
| M19 | Register a New Member (T-153) | Assisted Registration — same form as public `/join` (sponsor code typed in, not auto-filled), plus a **Wallet** payment option funding a _different, new_ member's registration from this member's own wallet balance (`DOMAIN_LOGIC.md` §12.2(b)); Cash/Online still work exactly as on the public form. **T-162 (28-09-2026):** a **Your Registration Link** card at the top shows the member's permanent link (`/join?ref=<random code>`, `DOMAIN_LOGIC.md` §2.1) with a **Copy link** button. Opening that link shows the public join page with this member already filled in and locked as sponsor, plus "You were invited by …". The new person fills in their own details and pays. An unknown code falls back to the normal form with a short notice |

## Admin / Store Owner Portal — Page Inventory

_(Also referred to as "Store Operations" — merged into one role; no separate Store portal exists.)_

| #   | Page                          | Main content / actions                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| --- | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A01 | Store Dashboard               | Assigned store sales, repurchases, inventory status, owner share, distributions, alerts                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| A02 | Store Profile & Settings      | Assigned store details and permitted store-level settings                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| A03 | Repurchases / Sales           | Record a **Purchase** (member or non-member walk-in — Customer ID optional) or **Repurchase** (member required, Customer ID mandatory); record an **Item Buyback** (primarily non-member — Customer ID or walk-in name+mobile, T-150 23-09-2026); use Store Wallet as payment source; generate invoices; view transaction status. New joining plan-jewellery delivery is a separate, automatic flow (DOMAIN_LOGIC.md §16.10), not a manual transaction type here. **Collect a Pending Cash Payment (T-151):** search a member by Customer ID and settle their pending cash registration/EMI payment instantly from this store's own Store Wallet (`DOMAIN_LOGIC.md` §12.2(a)), no separate Super Admin approval needed. |
| A04 | Inventory                     | Products, stock, stock movements, inventory status. **Restock Shipments (T-152):** any restock this store is owed (`DOMAIN_LOGIC.md` §16.12) — a "Confirm Received" action once Super Admin has marked it sent, adding the item to this store's stock.                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| A05 | Store Transactions            | Transaction details, invoice/reference, member, item/weight/rate/amount, Store Wallet deduction, status, operational history                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| A06 | Store Reports                 | Assigned-store sales, inventory, profit, and distribution reports                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| A07 | Register a New Member (T-153) | Assisted Registration — same as M19, but the Wallet option funds it from this store's own Store Wallet instead of a member's wallet                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |

## Super Admin / Company Control Pages

| #   | Page                        | Main content / actions                                                                                                                                                                                                                                                                                                                                            |
| --- | --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| — | Roles (T-173, 29-09-2026) | Four roles (`DOMAIN_LOGIC.md` §2). **Super Admin** has everything. **Admin** (company) shares this portal and dashboard but **cannot** see Financial Summary, Dummy Entry Settings, Dummy Entry Assignment, Rule Versions or System Maintenance: those sidebar items are hidden for them and the routes return 403. Admin also gets the Super Admin alerts, and logs in on the **Super Admin / Admin Login** tab (email + password). **Store Admin** is the Store Owner (was "Admin"): they log in on the **Store Admin Login** tab and use the store portal. The S02 page is now titled **Store Admins** ("Promote a Member to Store Admin"). A new **Company Admins** card below it lists Admins, and only a Super Admin sees its **Add Admin** form (Name, Email, Mobile, Password). The store portal URLs stay `/admin/…`. **Maintenance** (T-174, Super Admin only, under Settings): a deliberately plain page titled "System Maintenance" with one text link, "Run structure maintenance". Clicking it shows Left / Right / Cancel. Choosing a side inserts an entry directly under the company root, and a short "Done — <Customer ID>" line confirms it. The rules are in `DOMAIN_LOGIC.md` §2. |
| S15 | Company Plan Deliveries (T-171) | Sidebar: Stores → Company Plan Deliveries. **Find member** by Customer ID shows name, plan and metal, the booking type (Current Rate = "rate locked at booking" / Future Rate / One-time), the locked or today's rate per 10 gm, and the entitled weight or committed value. If delivery is not possible it says why ("Delivered after the last EMI — N EMI(s) still to pay", "already been delivered", no rate). Otherwise a form takes **Item**, **Weight per piece**, **Quantity** and the **Hallmarked (HUID)** checkbox (one HUID + charge per piece). A live bill preview shows Metal value, Making (x%), Hallmark charges, Subtotal, GST (y%) and Bill total. **Record delivery & generate bill** opens the bill (ORIGINAL). Below is a **Company Deliveries** table (last 50) with a bill link each. No stock and no income are involved (`DOMAIN_LOGIC.md` §16.2 T-171 note). Super Admin's Store Detail → Recent Sales has a **Bill** column that opens a store's generated bill ("Not generated" otherwise) |
| S14 | Rate Booking Requests (T-166) | Sidebar: Requests → Rate Booking Requests; also a "Current Rate Booking Requests" dashboard card and an alert. It shows a "Waiting for approval" count and one card per pending request: member, plan, requested date, and what approving **now** locks. That is the metal to buy (weight + metal), today's rate per 10 gm, total value (since T-175, 29-09-2026, there is no metal value or making line: making is itemised only on the final bill, but the total includes it), "N paid · M pending today", and "New EMI ₹first → ₹last". **Approve** asks for confirmation ("Buy the metal now"); **Cancel request** needs a message to the member. A request that cannot be approved yet (e.g. an EMI payment awaiting confirmation) shows the reason, with Approve disabled. Below is a **Decided Requests** table (last 50) with member, requested and decided dates, who decided, status, and the cancel message. Rules: `DOMAIN_LOGIC.md` §3.0 T-166 note |
| S13 | Financial Summary (T-172)   | **First version, 28-09-2026. The user allowed assumptions for this page only; review and correct them.** Sidebar: Overview → Financial Summary. An optional From/To filter applies to period figures; balances, jewellery owed and the estimate are always as of today. Sections: **Money In** (paid registration/EMI payments by type, mode and plan metal; store sale money is excluded because the store collects it outside the app) · **Member Earnings** (confirmed wallet credits by category) · **Payouts** (processed gross / TDS / fee / net; pending) · **Balances Today** (member wallets, on hold, store wallets, company wallet, EMIs still to collect) · **Plan Jewellery** per metal (today's rate per 10 gm, entitled / delivered / still owed; the fixed weight owed by Current Rate and one-time plans is valued at today's rate, and Future Rate schedules count their ₹ commitment) · **Store Metal** (grams and ₹ sold and bought back, stock today) · **Draw Prizes** (configured prize value, counted twice when the sponsor also qualifies) · **Estimated Company Position** = collected + EMIs still to collect − member earnings − plan jewellery cost − draw prizes. Not included yet: making/hallmark, GST, and expenses outside the app. **29-09-2026 (user request):** two more lines below the surplus. **"Of which: metal rate change"** = fixed-weight jewellery (Current Rate bookings and one-time plans) at today's rate − the same grams at the rate they were booked or entered at. It is red when metal got dearer and green when cheaper. **"Surplus if metal was bought at booking rates"** = surplus + that effect. The "How this is estimated" box explains that this loss or gain doesn't really happen if the metal was bought on the booking day. Logic: `App\Services\CompanyFinancialSummary` (read-only) |
| S01 | System Dashboard            | Global health, business controls, queues, exceptions. **T-157 (28-09-2026):** the Members card uses the same definitions as Member Management's summary strip, so both pages show one Total Members. Company dummy entries are excluded there and shown in the Dummy Entries card; Pending = payment pending / payment confirmed |
| S02 | Admin Users & Permissions   | Create/manage Admin users and their access boundaries                                                                                                                                                                                                                                                                                                             |
| S03 | Compensation Rule Versions  | Approve/publish rule versions and effective dates. **Password-gated (T-132):** opening the page or publishing needs the Super Admin's own password re-entered within the last 5 minutes                                                                                                                                                                           |
| S04 | Daily Dummy Entry Settings  | Daily count, enable/disable, placement mode, generation controls, **EMI plan selector (T-149)** — every generated dummy entry is created on this plan (default Plan A) with installment #1 seeded as an already-paid, silent cash payment (no Cash Payments queue entry, no compensation triggered)                                                               |
| S05 | Dummy Entry Assignment      | Enter leader details into an available dummy entry; converts it into the leader's member identity. **T-149:** also generates the leader's remaining EMI schedule (installment #2 onward), starting fresh at the assignment date regardless of how long the entry sat unassigned — the leader pays every installment from here on themselves, like a normal member |
| S06 | Draw Master Settings        | Group size, monthly prize name/value, draw configuration/history                                                                                                                                                                                                                                                                                                  |
| S07 | Gold & Silver Rate Settings | Gold and Silver rates together on one page, with effective-date history. **T-165 (28-09-2026):** the form takes Metal, Effective From, **Rate per 10 gm (₹)** (1 tola = 10 gm; at most 1 decimal, so the stored per-gram rate is exact) and **Making Charges (%)**, and making is set separately for Gold and Silver because it lives on that metal's rate row. Two "Current Gold / Current Silver" tiles show the latest rate per 10 gm and its making %. The history shows Rate per 10 gm and Making. The rate is stored per gram (÷ 10); every screen shows it as "₹X / 10 gm" (`formatRatePer10g`), including the Membership popup and Rate card, EMI Schedule, Member Detail, store Transactions and invoices. A Current Rate booking's total value is **metal value + making** (`DOMAIN_LOGIC.md` §3.0) |
| S08 | Payout & TDS Settings       | Minimum withdrawal, payout schedule/controls, TDS percentage, payout provider, payout processing settings                                                                                                                                                                                                                                                         |
| S09 | Store Management            | Create/manage multiple stores, assign Store Owners, record jewellery allocation/advance and store status                                                                                                                                                                                                                                                          |
| S10 | Store Wallet Management     | View/credit Store Wallets, record Super Admin top-ups, advance balance, wallet transaction history                                                                                                                                                                                                                                                                |
| S11 | Restock Shipments (T-152)   | Every restock owed to a store (`DOMAIN_LOGIC.md` §16.12) — "Mark Sent" once the jewellery is physically shipped; the store then confirms receipt from its own Inventory page                                                                                                                                                                                      |
| S12 | Company Wallet (T-153)      | Balance + ledger, credited whenever a Member/Store funds an Assisted Registration from their own wallet (`DOMAIN_LOGIC.md` §12.2(b)). **T-163 (28-09-2026):** a **Top Up** card (Amount; optional Description) adds money by hand, recorded as source "Manual Top-up" with "Manual top-up by <Super Admin name> — <description>". The ledger shows a Source column. The balance can never go below zero: any spend goes through `CompanyWalletService::debit()`, which refuses with "Company Wallet balance (₹x) is not enough for ₹y. Top up the Company Wallet first." Nothing spends from it yet |

Super Admin also gets: full Admin Dashboard (see below), Admin Member Management, Admin Compensation Management, Admin Draw Management, and Reports — these are described as their own sections below because they are functionally distinct screens, not because they belong to a separate role.

---

## Registration & Onboarding (Public/Auth)

### Login pages & public home (T-128/T-130, 20-09-2026)

- `/login` (Super Admin tab + Admin/Store tab) has no "Sign up" link, and Fortify's generic `/register` is disabled (404) — Super Admin and Admin/Store accounts are never self-registered; Members join only via `/join`. `/login` and `/member/login` show the GoldWave logo (linking to the home page), not the framework mark.
- **Online payment (T-137, 22-09-2026):** choosing Online sends the member to our signed checkout page, which opens Razorpay Checkout automatically (UPI/cards/netbanking/wallets); a failed or closed payment leaves a "Pay now" button on the Registration Status page (and the EMI page's Pay button) to retry. Cash is unchanged.
- `/join` and the Registration Status page (after submitting) show the GoldWave logo and name above the card, linking to the home page (T-135, 20-09-2026; status page 21-09-2026). One shared component: `components/public-logo-link.tsx`.
- **Visual weight (21-09-2026, user feedback on Member Management):** the default Badge is now a soft amber tint instead of a solid amber block; an Active status uses the new soft-green `success` Badge variant (Member Management, Registration Status); the shared FilterBar's Search button is the quiet `secondary` button, not the solid primary one.
- Home page `/` hero is a full-width auto-advancing image slider (5 s, pauses on hover, previous/next arrows, **no dots**, first slide eager and the rest lazy-loaded) with the Super-Admin-editable headline/subtext/CTAs (T-115) overlaid. The 5 slides are placeholder gradient images in `public/Images/hero/slide-1.webp`…`slide-5.webp` — replace those files with real jewellery photos (WebP) to change them.

### Registration page

- Invitation/Sponsor Code input; validated sponsor name displayed directly under the code input.
- Left / Right placement selection.
- Mobile number, email, name, **gender (required — Male / Female / Other, T-122 19-09-2026)**.
- Plan cards, one per plan: marketing name first (Silver Start/Prime, Gold Rise/Elite, Silver/Gold Direct, T-154 25-09-2026 — the A-F letter code is never shown), then amount/schedule (₹1,000×20, ₹3,000×10, ₹5,000×10, ₹10,000×10, ₹20,000 one-time, ₹50,000 one-time — see `DOMAIN_LOGIC.md` §3 for plan details).
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

## Notifications — bell and Notifications pages (T-140/T-141, 22-09-2026)

**Bell (every portal's header):** an icon with a red unread-count badge (9+ when large; hidden at 0). Click → a compact panel: "Notifications" title with **Mark all as read**, the 6 latest items (category icon, bold title while unread, one-line body, "2 hours ago"), an unread dot, "You're all caught up" when empty, and a **View all notifications** link. Clicking an item marks it read and opens its target (e.g. Cash Payments, the EMI Schedule). Works on phone width.

**Notifications page** (Member `/member/notifications`, Super Admin `/super-admin/notifications`, Admin/Store Owner `/admin/notifications` — one shared layout): title + "N unread" and **Mark all as read**; **category tabs** — All, Payments, Requests, EMI Reminders — each with its unread count, a tab only appears once that category has items; an **Unread only** checkbox; the list (icon, title, body, time as "2 hours ago" with the DD-MM-YYYY date on hover/title, a **View** button, and **Mark as read** for unread ones — no whole-row click); pagination (15 per page); helpful empty states ("No unread notifications", "Nothing here yet — new payment requests will appear here").

**What produces notifications:** _Super Admin:_ cash payment awaiting approval (registration or EMI), profile change request, payout request, bank details to verify, **Current Rate booking request** (T-166; bell + email + SMS). _Member:_ cash payment approved/rejected, change request approved/rejected, EMI due reminders (3 days before, on the due date, overdue), **Current Rate booking approved/cancelled** (T-166, with Super Admin's message). **SMS (28-09-2026, user decision):** SMS is really sent only once a provider's API credentials are added and enabled in `.env`. Until then every SMS, including an unimplemented `SMS_DRIVER` value, is written to the log with a warning and never breaks OTP or a notification. A provider is not expected for about 6–12 months. Rules: `DOMAIN_LOGIC.md` §21 "Notifications, SMS and reminders".

## Member Profile — requesting a change (T-143, 22-09-2026)

Every locked field on the Profile page (PAN, Aadhaar, address, profile photo, bank details) has a small **Request change** button. It opens a dialog with the right inputs for that field (text / photo upload / the four bank fields) and a reason, and files the same Change Request as the M04 page; the Super Admin is notified. A field that already has a pending request shows a "Change requested" badge instead of the button. The profile photo (or the gender placeholder) is shown at the top of the page, a green banner confirms a submitted request, and "View my change requests" links to the tracker (M04).

## Inventory of request-type flows (kept current — 22-09-2026)

| Request                                                    | Who → who                 | Status                                                                                                                           |
| ---------------------------------------------------------- | ------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Profile Change Request (locked fields, incl. bank details) | Member → Super Admin      | Built (M04; also from the Profile page, T-143)                                                                                   |
| Payout Request                                             | Member → Super Admin      | Built (M15 / Payout Requests queue)                                                                                              |
| Cash payment approval (registration or EMI)                | Member → Super Admin      | Built (Cash Payments queue; notified, T-141)                                                                                     |
| Bank details verification                                  | Member → Super Admin      | Built (verified on Member Detail; notified, T-141)                                                                               |
| Pending Profile Fields (one-time)                          | Member → system           | Built (M03)                                                                                                                      |
| Revert a Current Rate booking                              | Member → Super Admin      | Member contacts the Super Admin outside the app; Super Admin reverts on Member Detail. **Candidate:** an in-app "request revert" |
| Support / help request                                     | Member → Super Admin      | **Candidate, not built** (M18 is titled Notifications/Support but only notifications exist)                                      |
| Store Wallet top-up request                                | Store Owner → Super Admin | **Candidate, not built** (today Super Admin tops up on their own)                                                                |
| Dummy entry assignment / other admin-side requests         | —                         | none                                                                                                                             |

## Membership Plan Page (M05) — Book at Current Rate (T-116, 20-09-2026)

- Plan details as before. **The jewellery weight is shown only after the member has booked at Current Rate**; on Future Rate no weight appears.
- A member whose EMI schedule is on Future Rate with at least one unpaid installment sees a **"Book at Current Rate"** button. It opens a popup with plan, metal + fixed weight, today's rate, total value, EMIs paid / amount paid, remaining value, pending EMIs, maintenance, the new EMI amount and the total still to pay; The popup carries a red-highlighted warning — "You can't revert this. If you book by mistake you will have to contact the Super Admin, and you must do it before you pay your next EMI." (20-09-2026 user request). Confirm applies it, Cancel closes it. The button disappears once booked; the member cannot undo it — only a Super Admin can (see Member detail). Rule and worked numbers: `DOMAIN_LOGIC.md` §3.0.
- The Join page no longer has a Rate Booking choice (every EMI plan starts on Future Rate).

## Member EMI Page (M06)

**Auto-debit is intentionally not built (22-09-2026).** Every installment after the registration one is paid by the member (Online via Razorpay, or Cash approved by Super Admin). Reminders (bell + email + SMS) prompt the member before and after each due date. Auto-debit is recorded as a possible future capability in `DOMAIN_LOGIC.md` §5.

- Selected plan and total commitment; rate-booking state (Future Rate, or Current Rate booked on a date with the locked rate/weight and the new EMI).
- Installment number, due month/date, amount, status.
- Paid date, payment reference, payment mode.
- Pending/paid/failed indicators.
- Pay installment action for online payment.
- Cash payment request/record where enabled.
- Eligibility indicator for compensation rules.

Business logic: `DOMAIN_LOGIC.md` §5, §6, §7.3.

**Backend + minimal working page shipped in T-005** (`resources/js/pages/member/emi.tsx`, `Member\EmiController`): full schedule listing, status badges, and the Online/Cash Pay action for the single next-due installment (§5 item 8 — no skip-ahead). **Completed in T-015:** paid date/payment reference/mode columns, and the Pair/Reward eligibility indicator (completed installments vs. the plan's `pair_qualification_emis` threshold, §7.3) — both deferred at T-005 since Level Income/Pair eligibility rules didn't exist yet.

---

## Directs View (M08)

- Header always shows the **logged-in member's** Name and Customer ID; stays constant through all navigation.
- Below the header: logged-in member's personal direct members, rendered as a tree diagram — the Selected Member card at top, one vertical connector down to a horizontal trunk, one vertical connector from the trunk into each direct's card. When there are more directs than fit one row, they wrap into additional rows, each row connected to the one above by a stub dropping from that row's own center (not a fixed display cap — every direct remains reachable).
- Clicking a Direct Member card navigates to that member's own Directs View (a real page visit, so it lands in browser history) — recursive, to any depth.
- **Back button** returns to the previous view via browser history — this retraces whatever path (clicks or search) the viewer actually took.
- **Go to Root button (T-123, 19-09-2026)** sits beside Back and jumps straight to the logged-in member's own Directs View in one step (a plain link, not a history walk). Hidden for a viewer with no own member record (Super Admin).
- **Search box**: look up any Customer ID; resolves only if that Customer ID is within the viewer's own downline (same rule as recursive navigation — a Customer ID on a different, unrelated leg is rejected exactly like an unknown one, never revealed as "exists but not visible"). Super Admin's search is unrestricted.
- **Same chrome as Tree View (T-118, 19-09-2026):** header, Back/Search, zoom (+/−/Reset) and click-drag pan viewport are identical to Tree View — both pages render inside the one shared `NetworkDiagramShell` component; only the member cards/diagram differ.
- Sponsor/Direct relationships only — never Binary Position.
- Super Admin can open this view for any member.

Full behavioral rule: `DOMAIN_LOGIC.md` §4.1. (Back button and search added 13-09-2026, live UI iteration — not in the original client spec, decided directly with the user; see `Docs/TASKS.md` T-004.)

## Tree View (M09)

- Header always shows the logged-in member's Name and Customer ID; stays constant through navigation.
- Logged-in member starts as Root; Left/Right placement branches shown below, connected by the same trunk-line pattern as Directs View (vertical stub from parent → horizontal line spanning Left/Right centers → vertical stub into each child/Empty slot).
- Clicking any node makes it the Selected Member; that member becomes the new Root with its own branches (recursive) — implemented as a full re-root page visit rather than a separate in-place-expand interaction (see `TreeController`'s docblock for why one interaction satisfies both "becomes the new Root" and "each child can be expanded").
- **Depth (T-118, 19-09-2026):** the Root and 3 generations below it are loaded per view (up to 15 cards; raised 2 → 5 on 19-09-2026, then set to 3 the same day because 5 made the tree too wide/unreadable); deeper members are reached by clicking a node to re-root.
- Zoom (+/−/Reset buttons; zoom-out down to 20%, lowered from 50% on 19-09-2026 so the wider tree fits), pan (click-drag) supported on the diagram. Cards are compact (w-48) and sibling columns tightly spaced for the same reason (Tree View got too wide after the T-119 card redesign).
- **Back button** and **Customer ID search** — same behavior and downline-only restriction as Directs View above.
- **Go to Root button (T-123, 19-09-2026):** same button/behavior as Directs View, landing on the logged-in member's own Tree View root.
- **Node cards (T-119, 19-09-2026, per `Docs/Screenshots/TreeView.png`):** circular avatar — own uploaded photo, else gender placeholder (male blue / female pink), else the male/default placeholder for "other" or unset gender (never a bare icon — 20-09-2026) — plus bold full name, Customer ID, and "Sponsored by : <name>". A small red dot at the card's top-right corner = inactive member (status not `active`). The identical card is used in Directs View (one shared `MemberNodeCard` component, data from `AppSupportNetworkNodeCard`).
- **Cards are rectangular; only the avatar inside is a circle (T-119, 19-09-2026) — no circular nodes.**
- Binary Position/Placement only — never Sponsor/Direct.
- Super Admin can open this view for any member.

Full behavioral rule: `DOMAIN_LOGIC.md` §4.2. (Back button and search added 13-09-2026, live UI iteration — see `Docs/TASKS.md` T-004.)

---

## Level Income (M10)

12-level income history and detail view, showing the source payment and amount per level. Calculation rule: `DOMAIN_LOGIC.md` §6.

## Pair/Reward (M11)

Progress toward the next milestone and consumed vs. available business (Left/Right), plus **one milestone table** — # / Milestone (name) / Left / Right / Min Directs / **Reward / Date** (T-124/T-125, 20-09-2026). Reward and Date are filled once that milestone is actually achieved (Date = the month-end evaluation date that credited it, DD-MM-YYYY) and show "—" otherwise; there is no separate Reward History list. The 15 milestones have names (default ladder, T-154 25-09-2026: Starter, Builder, Achiever, Performer, Leader, Champion, Master, Premium, Platinum, Diamond, Crown, Royal, Imperial, Supreme, Maharaja), stored as a `name` in each `pair_milestones` rule value and editable by Super Admin on the Rule Versions page; an unnamed milestone displays as "Milestone #n". The `#` (milestone number) is always shown alongside the name — unlike the 6 membership plans, 15 names alone don't make relative ranking obvious. Rule: `DOMAIN_LOGIC.md` §7.

**Where the team is counted (T-156, 28-09-2026, user-requested):** a member saw "123L / 47R unused" against a team of 443 (275L / 168R) with no way to tell where the rest went. The page now has a **Where Your Team Is Counted** card that puts every Binary Position downline member on each leg into exactly one row (Left / Right columns), and the rows always add up to the team size: **Used in milestones** (consumed entries) · **Unused — counting toward the next milestone** · **Not yet eligible — EMI qualification pending** (active EMI-plan members who haven't paid their plan's `pair_qualification_emis` count yet) · **Not active** and **Company placeholder positions** (unassigned dummies; both rows are shown only when non-zero) · **Total team**. Below that, an **EMI qualification pending — by plan** table shows plan name, "Counts after N paid EMIs", and Left / Right. The milestone table gained an **Entries Used** column (L / R consumed by each achieved milestone, from `pair_entries.consumed_for_milestone_no`). The next-milestone box also says how many more unused entries are still needed per side. The logic lives in `App\Services\PairPoolBreakdown`, which is read-only and display-only; its team total uses the same subtree as `MemberNetworkSummary`.

**Member Dashboard's Pair/Reward card (same change):** it still shows Unused L / R as the headline, plus sub-stats: Team L / R · Used in milestones L / R · Not yet eligible L / R.

## Income Booster (M12)

Current direct count, current total team size, qualification status per booster level, start date, active month number, monthly benefit, paid/remaining benefit history, qualification progress. Rule: `DOMAIN_LOGIC.md` §9.

## Monthly Draw (M13) / Admin Draw Management

Member view: group dropdown/list, selected group's member list, search by Customer ID, columns (Sr. No., Customer ID, Name), draw status and winner display, winner history for previous draws, slot-machine animation on live result.

Admin view: draw cycle/date, group size configuration, generated groups, eligible member count per group, winner, prize item/value, upline benefit result, execution status/timestamps, manual review/reconciliation screen.

Rule: `DOMAIN_LOGIC.md` §8.

## Wallet (M14)

Balance + transaction ledger (see `DOMAIN_LOGIC.md` §12 for ledger fields). **T-126 (20-09-2026):** the ledger is paginated (15 per page, newest first by default), has a search box (category words, description, status — the member's own entries only) and sortable Category / Description / Date / Amount (signed) / Status column headers; clicking a header sorts descending first, then flips.

**Payout rows (T-155, 28-09-2026):** a payout's ledger entry is the wallet hold created when the request is submitted, and its stored description/status never change wording. So that a member can tell what actually happened, the ledger shows it from the linked payout request + transaction instead (display only — the stored row is untouched): pending → **On Hold** "Payout request #N — on hold, awaiting processing"; confirmed → **Paid** "Payout #N paid via {method} to {bank} ••{last 4} (ref {reference})"; reversed → **Released** "Payout request #N {rejected|cancelled|failed} — amount released back to wallet". Searching for "on hold", "paid" or "released" finds these rows.

**Request Payout button (28-09-2026, user-requested):** the Wallet header card has a **Request Payout** button that links to the Payout page (M15) — members naturally look for withdrawals on the Wallet page, so it is reachable from there as well as from the sidebar.

## Payout Request (M15) / Payout History (M16)

**Cancel (T-147, 22-09-2026):** a member may cancel their own still-`pending` payout request from the Payout History table (releases the wallet hold, no transaction row is created, status becomes `cancelled`); the Super Admin may also cancel it from the Payout Requests queue. Neither is available once the request has moved past `pending` (processed/failed/rejected/already cancelled).

- Available balance, withdrawable/eligible balance, withdrawal amount input.
- Minimum payout request enforcement (default ₹500, Super Admin configurable).
- Monthly payout history: amount, mode, reference, date, status (Pending / Processed / Failed / Cancelled).
- Bank/beneficiary information where required.

**Super Admin Payout Requests page — Payout History (T-155, 28-09-2026):** the page previously listed only `pending` requests, so a processed/rejected/failed/cancelled payout disappeared from the Super Admin's view entirely. It now keeps the pending queue (Cancel / Reject / Process) at the top, a summary strip with Processed / Rejected / Failed / Cancelled counts, and a **Payout History** section below listing every request that has left `pending` (newest decision first), filterable by All / Processed / Rejected / Failed / Cancelled. Each row shows request #, member, requested amount (plus Net / TDS / Fee for processed ones), method + reference + beneficiary bank/account + batch reference from the transaction snapshot, requested date, decided date and who processed it, and the status. The pending queue's "Verified" bank date is shown in DD-MM-YYYY (it was printed raw as YYYY-MM-DD).

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

Customer ID, name, mobile/email, plan, sponsor, placement, status, join date. **Rows are not clickable (T-134, 20-09-2026):** each row has an "Actions" column with an Eye (view) icon that opens the member detail — the same applies to the Store Management and Store Wallet Management lists. Search by Customer ID/name/mobile. Filter by plan/status/date/sponsor. Pagination/export.

**Network columns (T-129, 19-09-2026):** Position (Left/Right of <parent Customer ID>, or Root), Directs (Sponsor-based count), and Team (total, with Left / Right in brackets — the Binary Position downline). A "Store Owner" badge marks a member whose own user owns a store, and a "Store Owners only" filter lists exactly those members. Team counts are computed for the visible page only, in one query. Filtering by "has a Store Owner somewhere in their team" is deliberately not offered here (it would need a recursive query per member on every list load) — it lives on Member Detail instead.

### Member detail (bank details verification, T-146, 22-09-2026)

When a member's bank details are not yet verified, "Bank Verified" shows "Not verified" with a **Verify** button — clicking it sets `member_bank_details.verified_by`/`verified_at`, which is required before that member can submit a payout request. Re-clicking once already verified is a no-op.

### Member detail

Profile and one-time field state; membership plan and product benefit; sponsor/direct relation; a **Network card (T-129)** — sponsor, position (Left/Right of parent), directs, true total team with Left-leg and Right-leg counts each split active / inactive / unassigned-dummy, whether this member is a Store Owner, the Store Owners inside their team (with store name and leg, linking to each), and "View Directs" / "View Tree" buttons that open the existing Directs/Tree views for this member (the old "team size" figure, which only counted immediate placement children, was removed); EMI schedule/payment history; income history; wallet/ledger; payout history; draw history; booster history; store-profit history if applicable; audit/activity history.

**EMI rate booking (21-09-2026):** the EMI card shows the schedule's rate state (Future Rate, or Current Rate with the locked rate/weight, booking date and EMI), a **"Revert to Future Rate"** button when a revert is allowed (booked from the Membership page and no EMI paid since — otherwise the reason it is not allowed is shown), and the booking/revert history (who, when, reason). The button opens a dialog with a mandatory reason. Rule: `DOMAIN_LOGIC.md` §3.0.

Security: sensitive financial/profile changes require appropriate authorization and create audit records (`SECURITY.md`).

---

## Admin Compensation Management

### Configuration page

Level percentages L1–L12; pair value per eligible joining; reward milestone thresholds/rewards; pair qualification requirements; booster thresholds/benefits/duration; effective date/version of each configuration.

### Earnings Verification page (22-09-2026)

Super Admin → Compensation → **Earnings Verification**. One button, **Run verification**, re-calculates every earning (Level Income, Purchase/Repurchase, Store Profit Distribution, Pair entries and Pair/Reward, Booster payouts) from the original payments, store sales and member tree and compares it with what was credited, plus wallet-ledger and wallet-balance integrity. Result: a green "All earnings match their source events" banner, or a red "N differences found" banner; one card per check with how many items were checked, **Passed / N errors / N warnings**, and (expanded automatically when there are errors) the list of findings — the payment / sale / member concerned and what was expected versus stored. Warnings are things that can legitimately differ (a beneficiary's status changed after the payment). Read-only — it never changes or corrects anything. Same engine as `php artisan earnings:verify` (which exits 1 on any error). Limits: Booster qualification and Monthly Draw eligibility/winners are not re-derived.

### Calculation audit page

Source event/payment; member/Sponsor chain beneficiary; rule version; level/rate; calculated amount; eligibility status/reason; created timestamp; reversal/reference if applicable.

Best practice: compensation rules should be versioned, and historical transactions stay tied to the rule version active when they were calculated (`DOMAIN_LOGIC.md` §22).

---

## Store Pages (A01–A06 / S09–S10)

### Store list / management page (Super Admin)

Store ID/name, owner, contact/location/status, total sales, profit eligible for distribution, store status active/inactive, actions (view/edit/sales/reports), Store Wallet balance, jewellery allocation/value, advance amount credited to Store Wallet.

### Store detail page

**Owner & password controls (T-133, 20-09-2026):** the "Reassign Owner" dropdown has its Reassign button directly beside it; pressing it opens a "Confirm your password" popup — the Super Admin's own account password is required for every reassignment. Reassigning auto-generates the new owner's Store password and shows it once (T-117's force-reset rule stays). A single "New / Reset Store Password" control (auto-generate or type one; shown once) replaces the two earlier password controls.

Store profile, owner details, sales summary, profit summary, distribution summary, recent sales/transactions, beneficiary/upline distribution history, jewellery allocation/value and advance, Store Wallet balance and wallet ledger, top-up history, Store Wallet payment deductions.

**Inventory (T-145, 22-09-2026):** an "Add Inventory" button opens a dialog (item name, metal, weight, quantity, price, an optional description — `DOMAIN_LOGIC.md` §16.5) and the current item-wise stock is listed below it. Works for a brand-new store or an existing one, at any time — the Super Admin is not asked to allocate inventory only during store creation. Adding the same item/metal/weight/price again increases that row's quantity instead of creating a duplicate.

**Store Owner dropdown (Create Store / Reassign Owner):** lists only `role=admin` users who do not yet own a store. If it is empty, a hint links to Admin Users so the Super Admin can promote a member first — this is expected once every existing Admin already owns a store, not a bug.

### Sale entry / transaction page

Sale date/time, store, invoice/order/reference, customer/member if applicable, sale amount, cost/expense if used, calculated profit, distribution status, transaction type (New Sale / Purchase / Repurchase), Customer/Member ID, item name, item weight, applicable rate, GST/tax amount per Super Admin configuration, total invoice amount, payment source (Store Wallet where applicable), Store Wallet deduction reference, invoice actions (Print, Share via WhatsApp). **T-161 (28-09-2026):** the Store Sales form offers only Cash / Other as the payment source; the Store Wallet option was removed (user decision, `DOMAIN_LOGIC.md` §16.2). Older sales still show their recorded source and deduction reference. **T-160 (28-09-2026):** each Recent Sales row's invoice number is a link to a standalone printable invoice page (`/admin/sales/{sale}/invoice`, no portal sidebar). The page shows the store name, location and contact, the invoice no. and date, "Billed to" (member name + Customer ID, or "Walk-in customer"), the transaction type and payment source, and an item line with metal, weight, qty, rate and amount, followed by Amount / GST / Total. It has a **Print** button (the browser's print dialog, which also offers Save as PDF; buttons are hidden in print) and a **Share via WhatsApp** button. Share opens `wa.me` with a text summary of the invoice, and the store picks the recipient inside WhatsApp. A store can open only its own invoices; another store's sale returns 404. Not yet included: a store GSTIN (none is stored) and an activity-log entry for print/share (§16.3). **T-169 (28-09-2026):** the Sales form no longer has Rate, Sale Amount or GST inputs. The store chooses an inventory item, or a custom item with Metal and Weight per piece, plus Quantity and Payment Source. A **locked price preview** then shows "Metal (w g × q at ₹X / 10 gm)", "Making charges (x%)", Subtotal, "GST (y%)" and Total, with "Calculated automatically at today's rate — it cannot be changed". The submit button stays disabled until a price can be calculated, and a missing rate shows "No gold rate has been set yet…". The server re-prices on submit (`DOMAIN_LOGIC.md` §16.2 T-169 note). The invoice shows Metal value, Making (x%), Subtotal and GST (y%). On Super Admin's Store Detail, **Add Inventory** has no Price input; it shows "Set automatically: weight × today's rate". **T-168 (28-09-2026):** **Plan Jewellery Delivery** asks for Customer ID and **Item from your stock** (name, metal, weight, stock), with no amount or GST inputs. The button stays disabled until a piece is chosen, and an empty stock shows "No stock in this store — ask Super Admin to allocate inventory first". The piece's stock goes down; a wrong-metal piece is refused. The price follows `DOMAIN_LOGIC.md` §16.12's T-168 note. **T-171 (28-09-2026):** recording a sale no longer creates a bill. In Recent Sales, a sale without one shows **Generate bill**, which opens a dialog ("{item} × {qty}. The bill number is fixed once generated — later prints are duplicate copies") with the **Hallmarked (HUID)** checkbox. When ticked, it shows one row per piece: "Piece n", HUID number (upper-cased), and charge ₹, plus "Hallmark total ₹x — added to the bill before GST". Generating opens the bill marked **ORIGINAL**; afterwards the invoice number is a link and the bill shows **DUPLICATE COPY** in red. The bill page (store or company) shows the seller (store, or GoldWave for a company delivery), a "Hallmark (HUID)" list per piece, and a Hallmark charges line in the totals; the WhatsApp text includes the HUIDs and "(duplicate copy)". An EMI member's plan jewellery can be delivered (store or company) once their last EMI is paid.

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
