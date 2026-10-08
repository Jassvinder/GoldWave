# Deployment Runbook — Go-Live Guide

## Status

- Status: **Recommended plan written 02-10-2026 — hosting not bought yet, nothing deployed.** Section 1 is a recommendation, not a decision; record the final choice here when it is made.
- Last successful release verified: N/A
- Release owner / escalation: [TBD]

## 1. Hosting — what to buy

### What GoldWave needs from a server

- PHP 8.3 or newer (8.4 recommended; local dev runs 8.5) with `pgsql`, `mbstring`, `xml`, `bcmath`, `intl`, `gd` (with WebP), `zip`, `curl`.
- **PostgreSQL** (the app is built and tested only on PostgreSQL).
- A **queue worker that runs all the time** (`php artisan queue:work`, `QUEUE_CONNECTION=database`) and **cron** running `php artisan schedule:run` every minute. The scheduled jobs (`app/Console/Scheduling.php`, all Asia/Kolkata) are:
  - daily: EMI due statuses at 00:05, dummy entries at 00:30, booster payouts at 00:45, EMI reminders at 09:00;
  - monthly on the 15th: draw grouping at 00:00 and draw execution at 12:00;
  - month-end: pair milestones.
  - Without the worker and cron, none of them run.
- Node.js 24 + pnpm only to **build** the frontend (`pnpm run build`). The build also runs `php artisan wayfinder:generate`, so PHP must be present wherever it is built.
- HTTPS (passkey login only works over HTTPS on the real domain).

**Shared hosting will not work.** It has no always-on queue worker and usually no PostgreSQL. A **VPS** is the minimum.

### Options (prices checked 02-10-2026 — confirm on the provider's site before paying)

| Option | Spec | Price | Verdict |
| --- | --- | --- | --- |
| **Hostinger VPS KVM 2, Mumbai data centre** | 2 vCPU, 8 GB RAM, NVMe | ~₹799/month promo on a long term, ~₹1,199/month on renewal | **Recommended.** Cheapest server that comfortably runs PHP + PostgreSQL + worker on one machine, in India (low latency for members). |
| DigitalOcean Droplet, Bangalore (BLR1) | 2 vCPU, 4 GB RAM, 80 GB | from $24/month (~₹2,000), more with backups | Good, simple control panel and snapshots, but about 2× the cost for half the RAM. |
| AWS (EC2 + RDS) | — | Highest | **Not now** (user decision 02-10-2026: too costly at this stage). Revisit when traffic needs managed scaling. |
| Shared hosting | — | Cheap | Not suitable (see above). |

Optional: **Laravel Forge** (~$12/month Hobby plan) sets up Nginx, PHP, the queue worker, cron, SSL and one-click deploys on the VPS from a web panel. It saves server work, but it is not required — section 3 covers the same steps by hand.

Also needed: a **domain** (yearly), **business email/SMTP** for notification and password emails (any SMTP provider), and an uptime monitor (a free one is enough). SSL is free (Let's Encrypt).

Sources: [Hostinger VPS India price](https://zoutons.com/news/hostinger-vps-hosting-price-india-2026), [DigitalOcean pricing 2026](https://www.linuxteck.com/guides/digitalocean-review-2026/), [Laravel Forge pricing](https://benjamincrozat.com/laravel-forge).

## 2. Must do before going live

These come from the code as of 02-10-2026. Close each one, or record that it was accepted as is.

1. ~~**Uploaded documents are publicly reachable.**~~ **Done 02-10-2026 (T-206).**
   - Bank proofs and GPay/UPI screenshots are now on the private disk and open only for a logged-in Super Admin/Admin (`Docs/SECURITY.md`).
   - The migration `2026_10_02_000001_move_proof_files_to_private_disk` moves any older files.
   - Back up `storage/app/private` as well as `storage/app/public` (section 7).
2. **Seeded accounts use the password `password`.**
   - `SuperAdminUserSeeder` creates the Super Admin, and `CompanyRootMemberSeeder` creates the company root login.
   - Right after the first seed on the server, change both passwords, and change the Super Admin email from the `.test` address.
3. **Start from a fresh database.** Never copy the dev database to production: it holds demo and test data. Run only `php artisan db:seed --force`, which calls `DatabaseSeeder` and never `DemoNetworkSeeder`.
4. **Real business settings in Super Admin:**
   - metal rates (the seeded ones are placeholders);
   - Payment Settings (company UPI ID and QR);
   - Draw Settings prizes;
   - a review of every Rule Versions value;
   - Landing Page contact details (the defaults are drafts, T-203).
5. **Legal pages — drafts done 02-10-2026 (T-207):** `/terms`, `/privacy` and `/refund-policy` (no refund, no return).
   - Before live, have a legal adviser review the text.
   - Add the company's registered legal name and office to it if required.
   - When the text changes, update `LegalPageController::LAST_UPDATED`.
6. **Email:** set real `MAIL_*` SMTP values. Otherwise notification and password-reset emails only go to the log. SMS stays log-only until a provider is chosen (see Notifications below).
7. **Open client confirmations:** see `Docs/DOMAIN_LOGIC.md` §21 "Still open", plus the three plan-guide questions raised on 02-10-2026 in `Docs/TASKS.md` T-204.

## 3. One-time server setup (Ubuntu 24.04)

Run as a sudo user, not root. Names in `<>` are placeholders.

```bash
# Security basics
sudo adduser <deploy-user> && sudo usermod -aG sudo <deploy-user>   # then log in with an SSH key
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
sudo timedatectl set-timezone Asia/Kolkata

# PHP 8.4 + extensions, Nginx, PostgreSQL, Supervisor, Certbot
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
sudo apt install -y nginx postgresql supervisor certbot python3-certbot-nginx unzip git \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-bcmath \
  php8.4-intl php8.4-gd php8.4-zip php8.4-curl
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node 24 + pnpm (frontend build only)
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash - && sudo apt install -y nodejs
sudo corepack enable

# Database
sudo -u postgres psql -c "CREATE ROLE goldwave LOGIN PASSWORD '<strong-db-password>';"
sudo -u postgres psql -c "CREATE DATABASE goldwave OWNER goldwave;"
```

Turn on the provider's automatic VPS backups/snapshots as well (section 7 adds database backups on top).

## 4. First deploy

```bash
sudo mkdir -p /var/www/goldwave && sudo chown <deploy-user>:www-data /var/www/goldwave
git clone <private-repo-url> /var/www/goldwave      # use a read-only deploy key
cd /var/www/goldwave
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env                   # fill the values in the table below
php artisan key:generate
pnpm install --frozen-lockfile && pnpm run build
php artisan migrate --force
php artisan db:seed --force                         # DatabaseSeeder only — never DemoNetworkSeeder
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R <deploy-user>:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache
```

### Production `.env` (names only — never commit values)

| Variable | Production value |
| --- | --- |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://<your-domain>` (exact, with https) |
| `APP_TIMEZONE` | `Asia/Kolkata` |
| `LOG_LEVEL` | `warning` (or `error`) |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `pgsql`, `127.0.0.1`, `5432`, the database and role from step 3 |
| `SESSION_DRIVER` / `SESSION_ENCRYPT` / `SESSION_SECURE_COOKIE` | `database` / `true` / `true` |
| `QUEUE_CONNECTION` / `CACHE_STORE` | `database` / `database` |
| `FILESYSTEM_DISK` | `local` |
| `BROADCAST_CONNECTION` | `log` (the live draw push (`DrawResultPublished`) needs a broadcasting server such as Laravel Reverb, which is not set up — decide before the first draw) |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | your SMTP provider's values |
| `PAYMENT_ONLINE_ENABLED` | `false` (Cash + GPay/UPI only — see Razorpay below) |
| `NOTIFY_MAIL_ENABLED` / `NOTIFY_SMS_ENABLED` / `SMS_DRIVER` | `true` / `true` / `log` |
| `WALLET_OTP_SMS_ENABLED` | `false` until an SMS pack is bought (T-215: the wallet-payment code always goes by email; `true` also sends it by SMS) |

### Nginx site (`/etc/nginx/sites-available/goldwave`)

```nginx
server {
    listen 80;
    server_name <your-domain> www.<your-domain>;
    root /var/www/goldwave/public;
    index index.php;
    client_max_body_size 10M;          # payment screenshots, bank proofs

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/goldwave /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d <your-domain> -d www.<your-domain>    # free SSL, auto-renews
```

Point the domain's DNS `A` record (and `www`) to the VPS IP **before** running certbot.

### Queue worker (`/etc/supervisor/conf.d/goldwave-worker.conf`)

```ini
[program:goldwave-worker]
command=php /var/www/goldwave/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=<deploy-user>
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/goldwave/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

### Scheduler (cron, as `<deploy-user>`: `crontab -e`)

```cron
* * * * * cd /var/www/goldwave && php artisan schedule:run >> /dev/null 2>&1
```

## 5. First login after deploy

1. Log in at `/login` as Super Admin and change the email and password (section 2 item 2). Change the company root login's password too.
2. Fill in the business settings from section 2 item 4.
3. Create the Admin users and Stores.
4. Register one real test member through `/join` with Cash, approve it in Payment Approvals, and check the member's dashboard. Then decide whether to keep or cancel that test member.

## 6. Smoke checks after every release

- `/`, `/join`, `/login` and `/member/login` load over HTTPS, with no mixed-content warnings.
- Super Admin dashboard opens. `php artisan about` shows `production`, debug off, and caches on.
- `sudo supervisorctl status` shows the worker `RUNNING`, and `php artisan schedule:list` lists the jobs above.
- `php artisan queue:failed` is empty, and `storage/logs/laravel.log` shows no new errors.
- An uploaded GPay/UPI screenshot opens for the Super Admin.

## 7. Backups

- **Database, daily:** a cron entry such as `0 2 * * * pg_dump -U goldwave -h 127.0.0.1 goldwave | gzip > /var/backups/goldwave/db-$(date +\%d-\%m-\%Y).sql.gz`. Use a `.pgpass` file so the password is not in cron. Delete dumps older than 14 days.
- **Files:** back up all of `storage/app` together with the database dump. That covers `public` (profile photos, UPI QR) and `private` (bank proofs, payment screenshots, reports).
- **Off the server:** copy the backups off the VPS at least weekly (download them, or sync to cloud storage). A backup kept only on the same server is lost with the server.
- **Restore test:** restore one dump into a scratch database once before go-live, and again every few months.

## 8. Releasing an update

```bash
cd /var/www/goldwave
php artisan down                                   # maintenance page
# take a database backup first (section 7) whenever the release has migrations
git pull
composer install --no-dev --optimize-autoloader
pnpm install --frozen-lockfile && pnpm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart                          # worker picks up the new code
php artisan up
```

Then run the section 6 smoke checks.

**Rollback:**
1. `php artisan down`.
2. `git checkout <previous-commit>`.
3. Run the same install, build and cache steps.
4. If a migration already ran, restore the database backup taken just before the release. Do not hand-edit tables.
5. `php artisan up`.

Avoid releasing between 23:30 and 01:00, around 09:00, and on the 15th near 00:00 and 12:00. Scheduled jobs run at those times.

## Environments

| Environment | URL / service | Deploy trigger | Configuration source | Data/migration policy |
| --- | --- | --- | --- | --- |
| Local dev | `php artisan serve` / `.\gw dev` | manual | local `.env` | demo data allowed (`DemoNetworkSeeder`) |
| Production | `https://<your-domain>` on the VPS — [TBD until bought] | manual SSH deploy (section 8), or Forge if chosen | server `.env` only | fresh database, `DatabaseSeeder` only; forward-only migrations; backup before every migrating release |

Use names of secret variables, never their values.

## Online payments — Razorpay (T-137, 22-09-2026)

> **Switched off since 30-09-2026 (T-196, user decision — the fee is ~2.36% per payment).** Members pay by Cash or GPay/UPI, approved in Payment Approvals; the company UPI ID and QR are set in Super Admin → Payment Settings. To offer Razorpay again, set `PAYMENT_ONLINE_ENABLED=true` (plus the keys below) and clear the config cache — no code change is needed.

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
| `WALLET_OTP_SMS_ENABLED` | `false` (default) — the code a member enters to pay from their wallet (T-215, `config/otp.php`) goes by email only. Set `true` once a real SMS driver works, to also send it by SMS |

The **EMI reminder job** (`SendEmiReminders`, 09:00 Asia/Kolkata) and every other scheduled job need the Laravel scheduler running (`* * * * * php artisan schedule:run` in cron) **and** a queue worker (`php artisan queue:work` — `QUEUE_CONNECTION=database`), because scheduled jobs are queued. Without them no reminder goes out. Which notification goes to which channel is set in `config/notifications.php`.
