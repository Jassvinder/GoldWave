# Deployment Runbook — Go-Live Guide

## Status

- Status: **First deploy done 13-10-2026 on the client's existing Hostinger VPS (CyberPanel), in staging mode for live testing.** The server as it is actually set up is in section 0. Sections 3 to 8 are the generic Ubuntu/Nginx runbook; where they differ from section 0, section 0 wins for this server.
- Last successful release verified: 13-10-2026, commit `433898d` (site loads at `https://goldwave.life`, staging mode).
- Release owner / escalation: [TBD]

## 0. Production server as set up (13-10-2026)

The client already had a CyberPanel VPS running an old Laravel + MySQL site on `goldwave.life`. The new app replaced it on the same server (user decision 13-10-2026, the old site had no users).

| Item | Value |
| --- | --- |
| Server | Hostinger VPS, AlmaLinux 9.7, CyberPanel + OpenLiteSpeed (`lsws`), root SSH |
| Site user / home | `goldw8285` / `/home/goldwave.life` |
| App folder | `/home/goldwave.life/goldwave`. First install: the code directly in it (document root `goldwave/public`). After `.\gw deploy-setup`: the releases layout below (document root `goldwave/current/public`), and every path in this table that ends in `goldwave` becomes `goldwave/current` |
| PHP | `lsphp85` (8.5). The server's default `php` is 8.3 and the Composer platform check needs 8.4.1+, so **always run PHP and Composer as** `/usr/local/lsws/lsphp85/bin/php` (e.g. `sudo -u goldw8285 -H /usr/local/lsws/lsphp85/bin/php /usr/bin/composer install --no-dev --optimize-autoloader`), and as the site user, not root |
| Database | PostgreSQL 17 from the PGDG repo (`postgresql17-server`, service `postgresql-17`), database and role `goldwave`, localhost only (port 5432 is not opened). CyberPanel only manages MySQL, so PostgreSQL never shows in the panel. To browse it, use `sudo -u postgres psql goldwave` or DBeaver/pgAdmin over an SSH tunnel. Never install a web DB tool |
| Web server config | `/usr/local/lsws/conf/vhosts/goldwave.life/vhost.conf`: `docRoot $VH_ROOT/goldwave/public` and `path /usr/local/lsws/lsphp85/bin/lsphp`. Everything else (SSL, rewrite with `autoLoadHtaccess`) was kept |
| Queue worker | systemd unit `/etc/systemd/system/goldwave-worker.service` (user `goldw8285`, `queue:work --sleep=3 --tries=3 --max-time=3600`, `Restart=always`). Check it with `systemctl status goldwave-worker` |
| Scheduler | crontab of `goldw8285`: `* * * * * cd /home/goldwave.life/goldwave && /usr/local/lsws/lsphp85/bin/php artisan schedule:run` |
| Pre-switch backup | `/root/backup-13-10-2026` on the server (old site files, old MySQL DB `gold_golddb`, old `vhost.conf.bak`, old crontab), also copied to the user's PC. The old site is still in `/home/goldwave.life/public_html` |

**How it was deployed** (no Node or Git on the server):

1. Locally: `.\gw build`, then `git archive HEAD` plus `public/build` packed into `goldwave.tar.gz`, and `scp` from the PC to the server.
2. Extract into the app folder, `chown -R goldw8285:goldw8285`, `composer install --no-dev`.
3. `.env` from `.env.example` + `key:generate`. The DB password was generated with `openssl rand -hex 20`. Special characters in the password failed authentication from `.env`.
4. `migrate --force`, `db:seed --force`, `storage:link`, `optimize`.
5. Switch the vhost `docRoot` and PHP path, then `systemctl restart lsws`. If the site still reports the old PHP version, old `lsphp` processes are still running: `pkill -u goldw8285 -f lsphp` and restart `lsws`.

**Rollback to the old site:** copy `/root/backup-13-10-2026/vhost.conf.bak` back over `vhost.conf` and `systemctl restart lsws`.

### Releasing updates: `.\gw deploy` (14-10-2026)

The local side is in `gw.cmd` and the server side is `deploy/server-deploy.sh`. The script uploads itself on every deploy.

**Only committed code goes live.** The package is `git archive HEAD` plus a fresh `public/build`. If there are uncommitted changes, `gw deploy` lists them and asks before continuing. To keep a committed path off the server, add it to `.gitattributes` with `export-ignore`. `Docs/`, `tests/`, `deploy/` and the `.docx` files are already there.

Server layout (`/home/goldwave.life/goldwave`):

```
current -> releases/NNNN    the site, the worker and cron run from here
releases/0001, 0002, …      one full copy of the code per release; the last 3 are kept
shared/.env                 one copy, linked into every release
shared/storage              uploads, private proofs and logs; one copy, linked into every release
shared/db-backups           a pg_dump before every deploy; the last 10 are kept
```

| Command (on the PC) | What it does |
| --- | --- |
| `.\gw deploy-setup` | **One time.** Moves the first install into `releases/0001`, moves `.env` and `storage` into `shared/`, and points the vhost `docRoot`, the worker `WorkingDirectory` and the cron at `current/`. The site is down for a few seconds. The old settings are saved in `/root/goldwave-setup-backup` |
| `.\gw deploy` | Builds, packs, uploads, then on the server: unpack into a new release, link `shared/`, `composer install --no-dev`, DB dump, `migrate --force`, `storage:link`, `optimize`, switch `current`, restart LiteSpeed (and kill old `lsphp` processes), `queue:restart`, check that the site answers 200, and remove old releases. If any step before the switch fails, the half-built release is removed and the site stays on the old one |
| `.\gw deploy-rollback` | Switches `current` back to the release before it. **Code only:** if the newer release ran a migration, restore its dump from `shared/db-backups` by hand |
| `.\gw deploy-status` | Lists the releases and shows which one is live |

Each command asks for the root password once per `scp`/`ssh` call. An SSH key removes the prompts: run `ssh-keygen -t ed25519` on the PC, then add the `.pub` key to `/root/.ssh/authorized_keys` on the server.

**After any `.env` change:** `artisan optimize` and `artisan queue:restart` (as `goldw8285` with `lsphp85`).

**Staging phase, then go-live:**
- During testing the server runs `APP_ENV=staging`, `APP_DEBUG=false`, `MAIL_MAILER=log` and `SMS_DRIVER=log`. In staging, `php artisan test:entries` works (it refuses to run in `production`), and test members' fake emails and mobiles never receive mail or SMS.
- At go-live:
  1. `migrate:fresh --seed --force` (wipes all test data).
  2. Create the Super Admin again (below).
  3. Set `APP_ENV=production` and real `MAIL_*` values, then `optimize` and `queue:restart`.
  4. Do the section 2 items.

**Super Admin on the server:** `DatabaseSeeder` creates **no** Super Admin outside `local`/`testing` (`SuperAdminUserSeeder` returns early). `MetalRateSeeder` also skips when there is no Super Admin. On 13-10-2026 the Super Admin was created with `artisan tinker --execute` (`User::updateOrCreate`, role `super_admin`). The email and password were read with `read -s` into environment variables so they stay out of shell history. Then `db:seed --class=MetalRateSeeder --force` added placeholder rates.

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
   - A fresh install stores them there from the start. (The one-time migration that moved older files was removed on 08-10-2026 when migrations were merged; see `Docs/DATABASE_SCHEMA.md`.)
   - Back up `storage/app/private` as well as `storage/app/public` (section 7).
2. **Seeded accounts and the Super Admin.**
   - `CompanyRootMemberSeeder` creates the company root login with the password `password`. Change it right after the first seed on the server.
   - `SuperAdminUserSeeder` only runs in `local`/`testing`. On the server, the Super Admin is created by hand (section 0).
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
| Production | `https://goldwave.life` on the client's CyberPanel VPS (section 0). It runs as `APP_ENV=staging` until go-live | manual: build locally, upload a tarball over `scp` (section 0) | server `.env` only | fresh database, `DatabaseSeeder` only; test entries allowed only before go-live, then `migrate:fresh --seed`; forward-only migrations after go-live; backup before every migrating release |

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
