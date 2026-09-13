# Performance Guide

## Status

- Status: Active (agreed engineering standards from the client spec; no measured production numbers exist yet — this is pre-implementation)
- Last measured: N/A — no code/deployment exists yet
- Measurement environment / evidence: `GoldWave_Claude_instructions.docx`, "Code Quality, Architecture & Performance Standards" section, migrated here. Once the app is running, replace budget rows below with real measured numbers and update this Status block.

## Budgets and Hot Paths

| User/system path                                                                                                                                               | Target | Current evidence | Owner |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ | ---------------- | ----- |
| [TBD — fill in once the app is running and can be measured, e.g. Level Income calculation per payment event, Draw execution across all groups, Dashboard load] | [TBD]  | [TBD]            | [TBD] |

Do not invent numeric targets — this table stays empty/TBD until real measurements exist. The rules below are agreed engineering standards from the client, not measured budgets, and apply regardless of the numbers above.

## Operational Rules (agreed, from client spec)

### Database query performance

- N+1 queries and unnecessary database calls must be avoided.
- Avoid queries inside application loops wherever eager loading, joins, aggregations, grouping, subqueries, bulk operations, or better query design can get the same result.
- Do not load large datasets into application memory — filtering, searching, sorting, and aggregation happen at the database level wherever practical.
- Paginate large lists; plan database indexes according to actual search, filter, relationship, and reporting needs.
- Compensation calculations and reports must stay efficient as members, transactions, and financial records grow — this is a network-marketing platform where member/transaction counts can scale into the tens of thousands, and Level Income/Pair/Store-Distribution calculations walk relationship chains, so index and query design for `sponsor_id` / `placement_parent_id` chain-walks needs particular attention.

### Application performance & scalability

- Performance is a design requirement from day one, not a final-stage optimization pass.
- The application must stay responsive under high concurrent usage and increasing data volume.
- Expensive operations — large calculations, reports, exports, scheduled processing — must use queues/background processing (see `DOMAIN_LOGIC.md` §19 for the required background jobs and their idempotency requirements).
- Caching may be used where it gives a genuine benefit, but financial correctness and data consistency always take priority over a cache hit.
- Consider network requests, database workload, memory usage, payload size, and frontend rendering cost for every major feature.

### Specific hot paths implied by the domain

- **Monthly Draw execution** (`DOMAIN_LOGIC.md` §8.4): processes groups sequentially at a fixed time (15th, 12:00 PM); must complete reliably even with many groups/members and push results in real time to already-open Draw pages — plan for a queue-driven, retry-safe execution path plus a broadcast/real-time channel (e.g. Laravel broadcasting) rather than client polling.
- **Level Income / Purchase-Repurchase Income / Store Profit Distribution** (`DOMAIN_LOGIC.md` §6, §15, §16.4): each confirmed payment/sale can fan out into up to 12 ledger writes; batch these in a single database transaction per event rather than 12 separate round trips where possible.
- **Pair/Reward monthly evaluation** (`DOMAIN_LOGIC.md` §7): runs end-of-month across the full member base — a scheduled, queued batch job, not a per-request calculation.
- **Report exports** (`DOMAIN_LOGIC.md` / `INSTRUCTIONS.md` "Reports"): large CSV/Excel/PDF exports must be queued, never generated synchronously on the request thread.
- **Daily Dummy Entry generation** (`DOMAIN_LOGIC.md` §14): a daily scheduled job creating N placeholder members and placing them via the binary algorithm — must not lock the placement tree for unrelated registrations happening at the same time.

## Change Rules

Document only measured or agreed rules: query limits, cache ownership/TTL/invalidation, queue/retry policy, payload limits, rendering/assets, monitoring alerts, and load-test scenarios. Do not add speculative optimization guidance beyond what's captured above until it's backed by either a client requirement or a real measurement.
