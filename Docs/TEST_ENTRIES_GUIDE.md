# Test Entries Guide

How to add test members (for earnings testing) quickly, locally or on the live server.

> These are **test entries** made with the `test:entries*` commands. They are not the company's **daily dummy entries** feature (Super Admin → Dummy Entry Settings, `Docs/DOMAIN_LOGIC.md` §14), which is a real business feature.

Every test entry goes through the real registration flow: `RegisterMember`, then cash approval by the Super Admin. Level Income, Pair, Booster, Draw grouping and the other incomes therefore run exactly as for a real joining. The commands refuse to run when `APP_ENV=production`.

## 1. Before you start (live server)

1. The server must be in **staging** mode (`APP_ENV=staging` in `.env`). After go-live (`production`) the commands are locked. See `Docs/DEPLOYMENT.md` §0.
2. Keep `MAIL_MAILER=log` and `SMS_DRIVER=log` while testing. Test members get fake emails (`@goldwave.test`) and random mobiles.
3. A **Super Admin** must exist, because the commands use it to approve the cash payment. On the server it is created by hand (`Docs/DEPLOYMENT.md` §0).
4. Log in to SSH as root, then make a short alias once. It saves typing the long PHP path every time:

```bash
echo "alias gwa='sudo -u goldw8285 /usr/local/lsws/lsphp85/bin/php /home/goldwave.life/goldwave/current/artisan'" >> /root/.bashrc
source /root/.bashrc
gwa list test
```

This alias uses the releases layout (`current/`, see `Docs/DEPLOYMENT.md` §0). Before `.\gw deploy-setup` has been run, use `/home/goldwave.life/goldwave/artisan` instead.

`gwa list test` must show `test:entries`, `test:entries-file` and `test:entries-bulk`. Every `gwa …` below is the same as `php artisan …` locally.

## 2. The root entry (`GWL-ROOT`)

The company root member **GWL-ROOT** ("GoldWave") is created by `CompanyRootMemberSeeder`, which `db:seed` runs in every environment. On a fresh database it is the only member, so **the first test entry's sponsor is `GWL-ROOT`**. The first entry then gets Customer ID `GWL01`.

Check that it exists:

```bash
gwa tinker --execute='echo App\Models\Member::where("customer_id", "GWL-ROOT")->value("status") ?? "MISSING", PHP_EOL;'
```

- `active` means it is there.
- `MISSING` means you must create it: `gwa db:seed --class=CompanyRootMemberSeeder --force`. This also **resets the root login's password to `password`**, so change it again afterwards.

GWL-ROOT never earns (it is a company placeholder). Put test entries on its **left**. Its right side is where the daily dummy entries go when Super Admin switches them on.

## 3. Plans

The `plan` value is the plan code. If it is left empty, **E** is used.

| Code | Plan |
| --- | --- |
| A | Silver Start (EMI) |
| B | Silver Prime (EMI) |
| C | Gold Rise (EMI) |
| D | Gold Elite (EMI) |
| E | Silver Direct (one-time) — default |
| F | Gold Direct (one-time) |

## 4. A few entries: `test:entries`

```bash
gwa test:entries "GWL-ROOT:left,PREV:left,PREV:right"
gwa test:entries "GWL05:right" --plan=C
```

- Each item is `SPONSOR:side`.
- `PREV` means the entry added just before it in the same command.
- `--plan` applies to all entries of that run.

## 5. Many entries from a file: `test:entries-file`

### 5.1 Make the file

Make the file in Excel or Notepad. It has four columns: `sponsor, side, plan, name`. Sample: `Docs/test-entries-sample.csv`.

```
sponsor,side,plan,name
GWL-ROOT,left,,
GWL01,left,,
GWL01,right,,
#2,left,C,
PREV,right,,Ramesh Kumar
```

| Column | What to write |
| --- | --- |
| `sponsor` | An existing Customer ID (`GWL-ROOT`, `GWL05`), **or** `PREV` (the entry just above), **or** `#n` (entry number n of this same file, counting from the first entry and not counting the header). Use `#n` when the sponsor is a member added by this file, whose Customer ID you do not know yet. |
| `side` | `left` or `right` |
| `plan` | `A`–`F`, or empty for E (or for the `--plan=` given on the command) |
| `name` | Optional. If it is empty, the member is named "Test Member N" after its own Customer ID (GWL17 = "Test Member 17") |

- The header row is optional, and blank lines are skipped.
- In Excel, save with **File → Save As → CSV UTF-8 (Comma delimited)**.
- At most **2,000 entries per file**. Split bigger batches into several files.

### 5.2 Upload it (only for the live server)

Do this once, in the server SSH:

```bash
mkdir -p /home/goldwave.life/test-entries && chown goldw8285:goldw8285 /home/goldwave.life/test-entries
```

Then upload each file from the **PC's PowerShell**, not inside SSH:

```
scp D:\entries.csv root@76.13.191.232:/home/goldwave.life/test-entries/
```

### 5.3 Run it

```bash
gwa test:entries-file /home/goldwave.life/test-entries/entries.csv
```

- **The whole file is checked first.** If any line is wrong (bad side, unknown or inactive sponsor, `#n` pointing below itself, unknown plan), **nothing is added**. Every wrong line is listed with its line number. Fix them and run again.
- While running, it prints one line per entry: `#n Customer ID | name | sponsor | under (placement parent) side | plan`.
- The results are also saved next to the file as `entries-result.csv`. Download it with `scp root@76.13.191.232:/home/goldwave.life/test-entries/entries-result.csv D:\` from the PC.
- If an entry fails halfway, which is rare, the entries before it **stay added**. The message names the failed entry. Remove the entries that were already added from the file (they are in `entries-result.csv`), fix the failed one, and run the rest.

## 6. Many entries under one sponsor: `test:entries-bulk`

```bash
gwa test:entries-bulk --under=GWL-ROOT --side=left --count=50
gwa test:entries-bulk --under=GWL07 --side=right --count=200 --plan=A
```

- It adds `--count` entries (1 to 1,000), **all sponsored by `--under`, all on `--side`**.
- Placement follows the normal rule: straight down that side. So they form one long chain under that leg: the first goes directly under the sponsor, the next under the first, and so on.
- Each entry takes under about a second, so 100 entries take about 1–2 minutes.

## 7. Check the entries

- **In the app:** Super Admin → Member Management, and the members' Tree View.
- **In pgAdmin**, over an SSH tunnel (`Docs/DEPLOYMENT.md` §0). Only read data there, never edit it. Members with their sponsor:

```sql
SELECT m.customer_id, u.name, s.customer_id AS sponsor, p.customer_id AS placed_under, m.placement_side, m.status
FROM members m
LEFT JOIN users u ON u.id = m.user_id
LEFT JOIN members s ON s.id = m.sponsor_id
LEFT JOIN members p ON p.id = m.placement_parent_id
ORDER BY m.id;
```

- Month-end Pair/Reward, draw grouping and the Monthly Draw can be run on demand: `gwa jobs:month-end` and `gwa jobs:draw` (`Docs/TEST.md`). The queue worker must be running (`systemctl status goldwave-worker`).
- How to verify each income by hand: `Docs/EARNINGS_TESTING_GUIDE.md`.

## 8. Locally (dev machine)

The same commands work as `php artisan test:entries…` from the project root, with a file path on the PC:

```
php artisan test:entries-file D:\entries.csv
```

## 9. Before go-live

All test entries are removed by `migrate:fresh --seed --force`, run by hand **while the server is still in staging**. Production blocks that command. The full order is in `Docs/DEPLOYMENT.md` §0 "Go-live runbook".
