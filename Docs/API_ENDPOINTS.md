# API Contract

## Status

- Status: [TBD] — no code scaffolded yet; this file gets populated as endpoints are actually built.
- Last verified: N/A
- Contract owner / source: [TBD]
- Base URLs by environment: [TBD]
- Authentication scheme: [TBD] — the app is Laravel + Inertia.js + React (server-rendered routing, not a decoupled SPA), so most member/admin pages likely use Laravel's session-based auth rather than token auth. A future React Native app and any payment-gateway webhooks will need a separate, explicit auth/verification scheme — decide and document here before building either.

## Global Rules

| Concern                   | Contract                                                                                                                                                                                                                                 |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Versioning                | [TBD]                                                                                                                                                                                                                                    |
| Required headers          | [TBD]                                                                                                                                                                                                                                    |
| Request encoding          | [TBD]                                                                                                                                                                                                                                    |
| Response envelope         | [TBD]                                                                                                                                                                                                                                    |
| Pagination                | [TBD / Not applicable]                                                                                                                                                                                                                   |
| Error shape and codes     | [TBD]                                                                                                                                                                                                                                    |
| Rate limits / idempotency | Payment gateway webhooks and every scheduled job MUST be idempotent (`Docs/DOMAIN_LOGIC.md` §0, §19) — a hard business requirement, even though the exact mechanism (idempotency key header, DB unique constraint, etc.) is still [TBD]. |

## Endpoints

For each endpoint, record: method and path, purpose, auth/roles, request fields with validation, success response, error cases, side effects, pagination, and an example with redacted values.

_No endpoint contract documented yet — no backend code exists. See `Docs/DOMAIN_LOGIC.md` for the business rules every future endpoint must enforce server-side, and `Docs/INSTRUCTIONS.md` for the pages that will consume these endpoints._

## Change Rules

- Update this file with any intentional public or cross-service contract change.
- Do not document secrets, live tokens, or personal data.
- Link domain rules to DOMAIN_LOGIC.md instead of duplicating them.
