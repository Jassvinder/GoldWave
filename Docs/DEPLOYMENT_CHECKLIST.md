# Release Checklist

> Gate for every production release. Steps and commands are in `Docs/DEPLOYMENT.md`; tick each item or write why it was skipped.

## First go-live only

- [ ] Every item in `DEPLOYMENT.md` §2 "Must do before going live" is closed or explicitly accepted.
- [ ] Seeded Super Admin and company root passwords changed; Super Admin email changed.
- [ ] Production database is fresh (`DatabaseSeeder` only, no demo data).
- [ ] One database restore tested.

## Pre-release

- [ ] Scope and rollback path confirmed (previous commit hash noted).
- [ ] `php artisan test`, Pint, typecheck and build completed, or the exception recorded.
- [ ] New or changed `.env` variables reviewed (names only).
- [ ] Database backup taken if the release has migrations.
- [ ] Not inside a scheduled-job window (`DEPLOYMENT.md` §8).

## Release

- [ ] `DEPLOYMENT.md` §8 steps completed, including `php artisan queue:restart`.
- [ ] Released commit hash recorded.

## Post-release

- [ ] `DEPLOYMENT.md` §6 smoke checks passed.
- [ ] `storage/logs/laravel.log` and `php artisan queue:failed` reviewed.
- [ ] Rollback decision recorded if needed.
- [ ] `TASKS.md` and `PROGRESS.md` updated when applicable.
