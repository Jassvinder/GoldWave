# Documentation Router

Use this page to load the minimum reliable context for a task. Do not read every document by default.

> **Source note:** `../GoldWave_Claude_instructions.docx` is the original client specification. It has been fully read and migrated into the documents below (see the Documentation Status table). Treat the `.docx` as archival/reference-only from now on — `Docs/` is authoritative. There should be no need to re-open the `.docx` for normal work; if you find a gap between them, trust code/evidence first, then flag the discrepancy rather than silently picking one.

## Documentation Status

| Item                          | Status                                                                           | Last verified | Owner / evidence                                   |
| ----------------------------- | -------------------------------------------------------------------------------- | ------------- | -------------------------------------------------- |
| Project card                  | Active                                                                           | 09-09-2026    | ../AGENTS.md                                       |
| Task tracker                  | Active                                                                           | 09-09-2026    | TASKS.md                                           |
| Handoff log                   | Active                                                                           | 09-09-2026    | PROGRESS.md                                        |
| Architecture / flow           | Active                                                                           | 10-09-2026    | FLOWCHART.md, INSTRUCTIONS.md, ARCHITECTURE.md     |
| Data / API contracts          | Data model Active (implemented, T-002); API contract [TBD] (no endpoints yet)    | 10-09-2026    | DATABASE_SCHEMA.md, API_ENDPOINTS.md               |
| Domain, security, performance | Active                                                                           | 10-09-2026    | DOMAIN_LOGIC.md, SECURITY.md, PERFORMANCE_GUIDE.md |
| Testing / deployment          | Testing Active (commands + business-rule acceptance scenarios); deployment [TBD] | 10-09-2026    | TEST.md, DEPLOYMENT.md, DEPLOYMENT_CHECKLIST.md    |
| SEO                           | Not applicable                                                                   | 09-09-2026    | SEO_STRATEGY.md                                    |

Status values: Active, Not applicable, [TBD], or Archived. [TBD] means missing context, not an instruction to infer it. "Active (conceptual)" / "Active (commands TBD)" means the business/decision content is fully documented even though the code it will eventually verify against doesn't exist yet — re-verify those sub-parts once code exists.

## Task Router

| If the change involves…                                             | Read first                             |
| ------------------------------------------------------------------- | -------------------------------------- |
| Any work                                                            | TASKS.md; then this router             |
| Existing work / resuming a handoff                                  | PROGRESS.md                            |
| Feature behavior, pages, flows                                      | INSTRUCTIONS.md, FLOWCHART.md          |
| Business rules, calculations, states                                | DOMAIN_LOGIC.md                        |
| Schema, query, migration                                            | DATABASE_SCHEMA.md                     |
| Folder structure, service/action layering, jobs/events, permissions | ARCHITECTURE.md                        |
| API, webhook, external integration                                  | API_ENDPOINTS.md, SECURITY.md          |
| Authentication, privacy, permissions                                | SECURITY.md                            |
| Performance, queues, caching                                        | PERFORMANCE_GUIDE.md                   |
| Tests or QA                                                         | TEST.md                                |
| Build, configuration, release                                       | DEPLOYMENT.md, DEPLOYMENT_CHECKLIST.md |
| Search / public content                                             | SEO_STRATEGY.md                        |

Only read a routed document if its status is Active. If missing context blocks a material decision, ask rather than inventing it.

## Canonical Document Map

| File                    | Canonical content                                                                                      |
| ----------------------- | ------------------------------------------------------------------------------------------------------ |
| INSTRUCTIONS.md         | Feature requirements and acceptance criteria                                                           |
| FLOWCHART.md            | User/system journeys and decision points                                                               |
| DOMAIN_LOGIC.md         | Business rules, formulas, lifecycle states                                                             |
| DATABASE_SCHEMA.md      | Logical schema and data invariants                                                                     |
| ARCHITECTURE.md         | Code organization: layering, folder structure, compensation-engine isolation, jobs/events, permissions |
| API_ENDPOINTS.md        | Public/internal API contract                                                                           |
| SECURITY.md             | Threat-sensitive design and controls                                                                   |
| PERFORMANCE_GUIDE.md    | Budgets, bottlenecks, operational limits                                                               |
| TEST.md                 | Commands, coverage intent, QA scenarios                                                                |
| DEPLOYMENT.md           | Environment and release procedure                                                                      |
| DEPLOYMENT_CHECKLIST.md | Release gate                                                                                           |
| TASKS.md                | Work backlog and status                                                                                |
| PROGRESS.md             | Current handoff / blockers only                                                                        |
