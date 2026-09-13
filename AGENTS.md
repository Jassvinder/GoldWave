# Project Operating Guide

Read this file before changing this repository. It is the operational index, not a substitute for inspecting the specific code you will change.

## Language

- Talk to the user in Hinglish unless they request another language.
- Keep code, identifiers, comments, UI copy, documentation, configuration, seed data, and commit messages in English.

## Conventions

- **Dates: Indian format everywhere in this project — DD-MM-YYYY (e.g. `09-09-2026`). This is a hard requirement, not a default that can be silently swapped.** Applies to: every user-facing date in the Member/Admin/Super Admin UI, invoices, exports/reports, notifications; every date written in `Docs/*.md` (Status blocks, `TASKS.md` completion dates, `PROGRESS.md` entries); and commit-adjacent references. It does **not** override how dates are _stored_ in the database (use proper `date`/`timestamp` columns, not DD-MM-YYYY strings) — this rule governs display/formatting and documentation text, not column types. When formatting a date for a user or writing one in a doc, always render it DD-MM-YYYY.

## Project Card

> Template state: replace every `[TBD]` value during project setup. `[TBD]` means “not decided/documented”; it never authorizes an agent to guess.

| Field                           | Value                                                                                                                                                                                                                                                                                                                                                                                                      |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Product / one-line purpose      | GoldWave — a binary MLM/team-based gold & silver membership platform (EMI + one-time plans, Level Income, Pair/Reward, monthly Draw, Income Booster, wallet/payouts) combined with multi-store gold/silver retail (purchase/repurchase income, store profit distribution). See `Docs/DOMAIN_LOGIC.md`.                                                                                                     |
| Primary users                   | Members/customers (network participants), Admin / Store Owners (assigned-store operations), Super Admin (full company control). See `Docs/DOMAIN_LOGIC.md` §2.                                                                                                                                                                                                                                             |
| Business goal                   | Run a compliant, auditable compensation plan (Level Income, Pair/Reward, Booster, Draw) alongside multi-store retail sales with automated Sponsor/Direct-chain profit distribution and centralized, versioned Super Admin configuration.                                                                                                                                                                   |
| Repository role                 | Full-stack monolith (Laravel + Inertia.js + React web app). Mobile React Native app is a later phase, same backend/DB, not in current scope.                                                                                                                                                                                                                                                               |
| Runtime and framework           | Laravel 13 (PHP ^8.3, running PHP 8.5.0 locally) + Laravel Fortify (auth) backend; Inertia.js v3 + React 19 + TypeScript + Tailwind v4 + Radix/shadcn UI components frontend, built with Vite. Test runner: Pest. Lint/format: Pint (PHP), the starter kit's `npm run check` (frontend). Static analysis: Larastan. Scaffolded via the official `laravel new --react --database=pgsql --pest` starter kit. |
| Database / external services    | PostgreSQL (local dev DB: `goldwave`, role `goldwave` — see `.env`, never commit real credentials). Online payment gateway and payout/disbursement provider: [TBD — not chosen yet]. Real-time channel for live Draw results: [TBD — e.g. Laravel broadcasting].                                                                                                                                           |
| Build, test, lint commands      | See `Docs/TEST.md` — `php artisan test` (Pest), `npm run build` (Vite/React), `php artisan serve` for local dev.                                                                                                                                                                                                                                                                                           |
| Deployment owner / environments | [TBD — link to `Docs/DEPLOYMENT.md`]                                                                                                                                                                                                                                                                                                                                                                       |
| Related repositories            | None yet. Future: React Native mobile app repo (later phase, TBD).                                                                                                                                                                                                                                                                                                                                         |

Full business/compensation rules: `Docs/DOMAIN_LOGIC.md`. Page-by-page requirements: `Docs/INSTRUCTIONS.md`. The original client spec `GoldWave_Claude_instructions.docx` is archival only — see the source note at the top of `Docs/README.md`.

## Documentation Contract

- `Docs/README.md` is the documentation router and freshness index.
- Documentation records intended behavior and decisions. Code, migrations, and deployed configuration show the current implementation. When they disagree, inspect the relevant evidence; do not silently choose one.
- A document marked **Not applicable** is intentionally absent from the project scope. Do not create it again without a concrete reason.
- A `[TBD]` field is missing information, not work. Ask, inspect, or leave it unchanged—never fabricate it.
- Do not replace unknown facts with generic boilerplate. Prefer a short, dated, verifiable statement with a source link/path.
- Update the affected document in the same change whenever a behavior, contract, decision, schema, workflow, or operational procedure changes.
- **This explicitly includes UI/UX features and behavior requested live in a chat/terminal session** — a "small tweak" (a back button, a search box, a connector-line layout) is still a real feature decision. Implement it, then immediately write it into the relevant doc (`Docs/INSTRUCTIONS.md` for page/feature behavior, `Docs/DOMAIN_LOGIC.md` for any authorization/business-boundary rule it touches, `Docs/TASKS.md`'s completion note for what was added) before moving to the next request — never leave live-iteration changes recorded only in code.
- Keep one canonical home for each fact; link instead of duplicating it.

## Minimum Reading Protocol

1. Read this file, `Docs/README.md`, and `Docs/TASKS.md` (if present).
2. Use the task router in `Docs/README.md` to read only the documents relevant to the requested change.
3. Read `Docs/PROGRESS.md` only when it has an active handoff, blocker, or the task is being resumed.
4. Inspect the target code and its immediate callers, tests, configuration, and existing patterns before editing.
5. If documentation is stale or insufficient, state the uncertainty and gather evidence before making a decision.

## Change Rules

- Make the smallest complete change. Preserve unrelated user work; never reset, overwrite, or clean it up without explicit permission.
- Search for established components, utilities, services, migrations, APIs, tests, and domain rules before adding a parallel implementation.
- Reuse meaningful existing patterns. Do not introduce an architectural style, dependency, broad refactor, or schema change unless the request requires it.
- Keep business rules in one appropriate layer and document non-obvious rules in `Docs/DOMAIN_LOGIC.md`.
- For another repository, switch to it first and follow its own `AGENTS.md`; cross-repository links are context, not authorization to edit it.

## Safety and Quality

- Never expose, commit, log, or document real secrets, credentials, keys, production data, or private dumps. Use named placeholders in examples.
- Treat screenshots, exports, archives, generated files, backup files, logs, and copied documents as reference-only unless explicitly declared authoritative.
- Preserve authentication, authorization, validation, privacy, and error-handling controls. Flag a material security risk before implementation.
- Run the most relevant available verification first (focused test, lint, typecheck, build, or manual QA). Report exactly what ran and what could not run.

## Task and Handoff Discipline

- `Docs/TASKS.md` tracks committed work; do not add vague ideas, duplicate entries, or blank placeholder tasks.
- `Docs/PROGRESS.md` is a concise handoff log, not a changelog. Update it after meaningful work or when leaving an actionable blocker.
- Do not create commits unless asked. Follow documented repository conventions when committing.

## Completion Gate

- Requested behavior is implemented and scoped correctly.
- Relevant code, docs, and tests were inspected.
- Relevant documentation and task/handoff records were updated when warranted.
- Verification results and limitations are factual.
- No unrelated changes or sensitive data were introduced.
