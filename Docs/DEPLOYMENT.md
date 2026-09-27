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

## Online payments — Razorpay (T-137, 22-09-2026)

Environment variables (set in the server's `.env`; never commit them):

| Variable                  | Meaning                                                                                                                                        |
| ------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `RAZORPAY_KEY_ID`         | Razorpay API Key Id (`rzp_test_…` for test mode, `rzp_live_…` for live)                                                                        |
| `RAZORPAY_KEY_SECRET`     | Razorpay API Key Secret (shown once in the dashboard when generated)                                                                           |
| `RAZORPAY_WEBHOOK_SECRET` | The secret you type when creating the webhook in the Razorpay dashboard (it is not the key secret)                                             |
| `PAYMENT_GATEWAY`         | Optional override: `razorpay` or `fake`. Leave unset — Razorpay is used automatically when both keys are set. `fake` is refused in production. |

Razorpay Dashboard setup: (1) Settings → API Keys → generate a Test key first; (2) Settings → Webhooks → add `https://<your-domain>/payments/webhook`, choose a webhook secret, subscribe to **`order.paid`**, **`payment.captured`** and **`payment.failed`**; (3) leave payment capture on **automatic** (the app also captures an authorized payment through the API if it is left on manual); (4) test with Razorpay's test cards/UPI, then generate Live keys and replace the three variables. A webhook cannot reach `localhost` — locally the return-path verification confirms payments without it (use a tunnel only to test the webhook).

## Notifications, SMS and the scheduler (T-139…T-142, 22-09-2026)

| Variable              | Meaning                                                                                                                                                                                                   |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `NOTIFY_MAIL_ENABLED` | `true` (default) — send the email channel of notifications; needs a working `MAIL_*` configuration (locally `MAIL_MAILER=log` just writes the mail to `storage/logs`)                                     |
| `NOTIFY_SMS_ENABLED`  | `true` (default) — send the SMS channel of notifications                                                                                                                                                  |
| `SMS_DRIVER`          | `log` (default and the only driver so far) — SMS is written to the log, nothing is sent. A real provider (MSG91 / Twilio / Fast2SMS …, DLT template ids for India) is added as another driver once chosen |

The **EMI reminder job** (`SendEmiReminders`, 09:00 Asia/Kolkata) and every other scheduled job need the Laravel scheduler running (`* * * * * php artisan schedule:run` in cron) **and** a queue worker (`php artisan queue:work` — `QUEUE_CONNECTION=database`), because scheduled jobs are queued. Without them no reminder goes out. Which notification goes to which channel is set in `config/notifications.php`.
