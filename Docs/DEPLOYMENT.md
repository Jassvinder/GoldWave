# Deployment Runbook

## Status

- Status: [TBD] — no environment, hosting, or CI/CD decided yet; project not scaffolded.
- Last successful release verified: N/A
- Release owner / escalation: [TBD]

Whatever environment is chosen must run a persistent queue worker and the Laravel scheduler (cron → `schedule:run`), not just serve HTTP requests — `Docs/DOMAIN_LOGIC.md` §19 requires several always-on background jobs (draw grouping at 15th 00:00, draw execution at 15th 12:00, daily dummy-entry generation, booster payouts, report exports). Confirm the server timezone before wiring these — see the open item in `Docs/DATABASE_SCHEMA.md`'s Data Conventions table.

## Environments

| Environment | URL / service | Deploy trigger | Configuration source | Data/migration policy |
| ----------- | ------------- | -------------- | -------------------- | --------------------- |
| [TBD]       | [TBD]         | [TBD]          | [TBD]                | [TBD]                 |

## Release Procedure

1. Preconditions: [TBD]
2. Build and verification commands: [TBD]
3. Deployment action: [TBD]
4. Migration / background job action: [TBD]
5. Smoke checks and monitoring: [TBD]
6. Rollback trigger and procedure: [TBD]

Use names of secret variables, never their values.
