#!/usr/bin/env bash
# GoldWave server release script (14-10-2026). Runs as root on the CyberPanel VPS. See Docs/DEPLOYMENT.md §0.
#
#   bash goldwave-deploy.sh setup              one time: turn the first plain install into releases/shared/current
#   bash goldwave-deploy.sh deploy <tar.gz>    install a package from `gw deploy` as a new release and switch to it
#   bash goldwave-deploy.sh rollback           switch back to the release before the current one (code only)
#   bash goldwave-deploy.sh status             show the releases and which one is live
#
# Layout:  $APP/current -> releases/NNNN   (the site, worker and cron run from current)
#          $APP/releases/NNNN              one full copy of the code per release, the last $KEEP are kept
#          $APP/shared/.env, shared/storage one copy, linked into every release
#          $APP/shared/db-backups          a database dump before every deploy, the last 10 are kept
set -Eeuo pipefail

APP=/home/goldwave.life/goldwave
SITE_USER=goldw8285
PHP=/usr/local/lsws/lsphp85/bin/php
VHOST=/usr/local/lsws/conf/vhosts/goldwave.life/vhost.conf
WORKER=/etc/systemd/system/goldwave-worker.service
SITE_URL=https://goldwave.life
KEEP=3

say() { echo -e "\n==> $*"; }
fail() { echo -e "\nERROR: $*" >&2; exit 1; }
artisan() { (cd "$1" && sudo -u "$SITE_USER" "$PHP" artisan "${@:2}"); }

restart_php() {
    # LiteSpeed keeps old lsphp processes alive after a restart; they would keep serving the old code.
    systemctl restart lsws
    pkill -u "$SITE_USER" -x lsphp || true
}

current_release() { readlink "$APP/current" 2>/dev/null | xargs -r basename; }

releases() { ls -1 "$APP/releases" 2>/dev/null | sort; }

link_shared() {
    local dir=$1
    rm -rf "$dir/storage" "$dir/.env"
    ln -s "$APP/shared/storage" "$dir/storage"
    ln -s "$APP/shared/.env" "$dir/.env"
}

switch_to() {
    local name=$1
    ln -sfn "releases/$name" "$APP/current.new"
    mv -T "$APP/current.new" "$APP/current"
    chown -h "$SITE_USER:$SITE_USER" "$APP/current"
    restart_php
    artisan "$APP/current" queue:restart # the worker finishes its job, exits, and systemd starts it again in current
}

check_site() {
    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$SITE_URL" || echo 000)
    if [[ $code == 200 ]]; then
        echo "Site check: $SITE_URL answered 200."
    else
        echo "WARNING: $SITE_URL answered $code. Check it now; to go back run:  bash $0 rollback"
    fi
}

cmd_setup() {
    [[ -L $APP/current ]] && fail "Already set up ($APP/current exists)."
    [[ -f $APP/artisan && -f $APP/.env ]] || fail "$APP does not look like the first plain install."

    say "Backing up vhost, worker and crontab"
    local backup=/root/goldwave-setup-backup
    mkdir -p "$backup"
    cp "$VHOST" "$backup/vhost.conf"
    cp "$WORKER" "$backup/goldwave-worker.service"
    crontab -u "$SITE_USER" -l > "$backup/crontab.txt"

    say "Moving the install into releases/0001 (the site is down for a few seconds)"
    mv "$APP" "$APP-first"
    mkdir -p "$APP/releases" "$APP/shared/db-backups"
    mv "$APP-first" "$APP/releases/0001"
    mv "$APP/releases/0001/.env" "$APP/shared/.env"
    mv "$APP/releases/0001/storage" "$APP/shared/storage"
    link_shared "$APP/releases/0001"
    ln -s releases/0001 "$APP/current"
    chown -R "$SITE_USER:$SITE_USER" "$APP"
    chmod 700 "$APP/shared/db-backups"

    say "Pointing the website, worker and cron at current/"
    sed -i "s#^docRoot .*#docRoot                   \$VH_ROOT/goldwave/current/public#" "$VHOST"
    sed -i "s#^WorkingDirectory=.*#WorkingDirectory=$APP/current#" "$WORKER"
    echo "* * * * * cd $APP/current && $PHP artisan schedule:run >> /dev/null 2>&1" | crontab -u "$SITE_USER" -
    systemctl daemon-reload

    say "Rebuilding caches and the storage link for the new paths"
    rm -f "$APP/releases/0001/public/storage"
    artisan "$APP/releases/0001" storage:link
    artisan "$APP/releases/0001" optimize
    restart_php
    systemctl restart goldwave-worker

    say "Setup done. Old settings are in $backup"
    grep '^docRoot' "$VHOST"
    check_site
}

cmd_deploy() {
    local package=${1:-}
    [[ -f $package ]] || fail "Package not found: $package"
    [[ -L $APP/current ]] || fail "Run setup first:  bash $0 setup"

    local last next dir
    last=$(releases | tail -1)
    next=$(printf '%04d' $((10#${last:-0} + 1)))
    dir=$APP/releases/$next

    # Any failure before the switch removes the half-built release; the site stays on the current one.
    trap 'rm -rf "$dir"; echo "Deploy failed. Release $next was removed; the site is still on $(current_release). If migrations had started, the dump taken before them is in $APP/shared/db-backups."' ERR

    say "Unpacking release $next"
    mkdir -p "$dir"
    tar -xzf "$package" -C "$dir"
    link_shared "$dir"
    chown -R "$SITE_USER:$SITE_USER" "$dir"

    say "composer install"
    (cd "$dir" && sudo -u "$SITE_USER" -H "$PHP" /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction)

    say "Database backup before migrating"
    local dump
    dump=$APP/shared/db-backups/before-$next-$(date +%d-%m-%Y_%H%M).sql.gz
    (cd /tmp && sudo -u postgres pg_dump goldwave) | gzip > "$dump"
    ls -1t "$APP"/shared/db-backups/*.sql.gz | tail -n +11 | xargs -r rm -f
    echo "Saved $dump"

    say "Migrations and caches"
    artisan "$dir" migrate --force
    artisan "$dir" storage:link
    artisan "$dir" optimize

    say "Switching the site to release $next"
    trap - ERR
    switch_to "$next"
    rm -f "$package"

    say "Removing old releases (keeping $KEEP)"
    local live
    live=$(current_release)
    releases | head -n -"$KEEP" | while read -r old; do
        [[ $old == "$live" ]] || { rm -rf "${APP:?}/releases/$old"; echo "Removed $old"; }
    done

    say "Release $next is live"
    check_site
}

cmd_rollback() {
    local live previous
    live=$(current_release)
    previous=$(releases | awk -v live="$live" '$0 < live' | tail -1)
    [[ -n $previous ]] || fail "No release before $live to go back to."

    say "Switching from $live back to $previous"
    switch_to "$previous"
    echo "Code is back on $previous. The database is NOT rolled back."
    echo "If $live ran a migration, restore its dump from $APP/shared/db-backups (newest: before-$live-*)."
    check_site
}

cmd_status() {
    echo "Live: $(current_release)"
    releases | sed 's/^/  release /'
}

case ${1:-} in
    setup) cmd_setup ;;
    deploy) cmd_deploy "${2:-}" ;;
    rollback) cmd_rollback ;;
    status) cmd_status ;;
    *) sed -n '2,13p' "$0"; exit 1 ;;
esac
