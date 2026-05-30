#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
pass() { echo -e "  ${GREEN}✓${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; }
warn() { echo -e "  ${YELLOW}⚠${NC} $1"; }
info() { echo -e "  ${CYAN}→${NC} $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_NAME="autodns"
FIX="${1:-}"
[[ "$FIX" == "--fix" || "$FIX" == "-f" ]] && FIX_MODE=true || FIX_MODE=false
PASS=0; FAIL=0; WARN=0; FIXED=0

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN} Auto DNS Domain — Health Check${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "  Direktori: ${APP_DIR}"
$FIX_MODE && echo -e "  ${YELLOW}Mode: Auto-Fix${NC}" || echo -e "  ${YELLOW}Mode: Read-only (tambah --fix untuk auto-fix)${NC}"
echo ""

try_fix() {
    local desc="$1"; shift
    if ! $FIX_MODE; then return 1; fi
    if "$@" 2>/dev/null; then
        pass "${desc} (fixed)"; FIXED=$((FIXED+1)); return 0
    else
        warn "${desc} — gagal auto-fix. Manual: $*"; WARN=$((WARN+1)); return 1
    fi
}

detect_ws() {
    if command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then echo "nginx"
    elif command -v apache2 &>/dev/null && systemctl is-active --quiet apache2 2>/dev/null; then echo "apache"
    else echo "unknown"; fi
}

detect_ws_user() {
    if [[ -f /etc/apache2/envvars ]]; then
        local u; u=$(grep -oP '^export APACHE_RUN_USER=\K.+' /etc/apache2/envvars 2>/dev/null | tr -d '"')
        [[ -n "$u" ]] && echo "$u" && return 0
    fi
    if command -v ps &>/dev/null; then
        local u
        u=$(ps -o user= -C apache2 2>/dev/null | head -1)
        [[ -n "$u" ]] && echo "$u" && return 0
        u=$(ps -o user= -C nginx 2>/dev/null | head -1)
        [[ -n "$u" ]] && echo "$u" && return 0
    fi
    echo "www-data"
}

# ── 1. Files & Permissions ──
echo -e "${YELLOW}── Files & Permissions ──${NC}"

if [[ -f "${APP_DIR}/artisan" ]]; then
    pass "artisan found"; PASS=$((PASS+1))
else
    fail "artisan missing — not a Laravel project"; exit 1
fi

if [[ -f "${APP_DIR}/.env" ]]; then
    pass ".env exists"; PASS=$((PASS+1))
else
    if try_fix ".env missing" cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"; then
        pass ".env created"; PASS=$((PASS+1))
    else
        fail ".env missing — cp .env.example .env"; FAIL=$((FAIL+1))
    fi
fi

if grep -q "APP_KEY=" "${APP_DIR}/.env" 2>/dev/null && ! grep -q "APP_KEY=$" "${APP_DIR}/.env" 2>/dev/null; then
    pass "APP_KEY is set"; PASS=$((PASS+1))
else
    if try_fix "APP_KEY not set" php "${APP_DIR}/artisan" key:generate --force --quiet; then
        pass "APP_KEY generated"; PASS=$((PASS+1))
    else
        fail "APP_KEY not set — php artisan key:generate"; FAIL=$((FAIL+1))
    fi
fi

DB_FILE="${APP_DIR}/database/database.sqlite"
if [[ -f "$DB_FILE" ]]; then
    pass "SQLite database exists"; PASS=$((PASS+1))
else
    if try_fix "database.sqlite missing" cp "${APP_DIR}/database/sample.sqlite" "$DB_FILE"; then
        pass "database.sqlite created"; PASS=$((PASS+1))
    else
        fail "database.sqlite missing"; FAIL=$((FAIL+1))
    fi
fi

for dir in storage bootstrap/cache database; do
    if [[ -w "${APP_DIR}/${dir}" ]]; then
        pass "${dir} is writable"; PASS=$((PASS+1))
    else
        ws_u=$(detect_ws_user)
        if try_fix "${dir} permissions" sudo chown -R "${ws_u}:${ws_u}" "${APP_DIR}/${dir}"; then
            pass "${dir} permissions fixed"; PASS=$((PASS+1))
        else
            fail "${dir} not writable — sudo chown -R ${ws_u}:${ws_u} ${dir}"; FAIL=$((FAIL+1))
        fi
    fi
done

# ── 2. Web server ──
echo ""
echo -e "${YELLOW}── Web Server ──${NC}"

WS=$(detect_ws)
case "$WS" in
    nginx)
        pass "Nginx is running"; PASS=$((PASS+1))
        if [[ -f /etc/nginx/sites-enabled/${APP_NAME} ]]; then
            pass "Nginx vhost enabled"; PASS=$((PASS+1))
        elif [[ -f /etc/nginx/sites-available/${APP_NAME} ]]; then
            if try_fix "vhost not enabled" sudo ln -sf "/etc/nginx/sites-available/${APP_NAME}" "/etc/nginx/sites-enabled/" && sudo systemctl reload nginx; then
                pass "Nginx vhost enabled (fixed)"; PASS=$((PASS+1))
            else
                warn "Vhost exists but not enabled — sudo ln -sf ../sites-available/${APP_NAME} /etc/nginx/sites-enabled/"; WARN=$((WARN+1))
            fi
        else
            fail "Nginx vhost not found — run install.sh"; FAIL=$((FAIL+1))
        fi
        ;;
    apache)
        pass "Apache is running"; PASS=$((PASS+1))
        if [[ -f /etc/apache2/sites-enabled/${APP_NAME}.conf ]]; then
            pass "Apache vhost enabled"; PASS=$((PASS+1))
        elif [[ -f /etc/apache2/sites-available/${APP_NAME}.conf ]]; then
            if try_fix "vhost not enabled" sudo a2ensite "${APP_NAME}.conf" && sudo systemctl reload apache2; then
                pass "Apache vhost enabled (fixed)"; PASS=$((PASS+1))
            else
                warn "Vhost exists but not enabled — sudo a2ensite ${APP_NAME}.conf"; WARN=$((WARN+1))
            fi
        else
            fail "Apache vhost not found — run install.sh"; FAIL=$((FAIL+1))
        fi
        if apache2ctl -M 2>/dev/null | grep -q 'ssl_module'; then
            pass "Apache SSL module enabled"; PASS=$((PASS+1))
        else
            if try_fix "SSL module not enabled" sudo a2enmod ssl && sudo systemctl reload apache2; then
                pass "SSL module enabled (fixed)"; PASS=$((PASS+1))
            else
                warn "SSL module not enabled — sudo a2enmod ssl"; WARN=$((WARN+1))
            fi
        fi
        ;;
    *)
        fail "No web server detected (nginx/apache)"; FAIL=$((FAIL+1))
        ;;
esac

# ── 3. PHP-FPM ──
echo ""
echo -e "${YELLOW}── PHP-FPM ──${NC}"

FPM_SERVICE=$(systemctl list-units --type=service --state=running 2>/dev/null | grep 'php.*fpm' | head -1 | awk '{print $1}' || true)
if [[ -n "$FPM_SERVICE" ]]; then
    pass "PHP-FPM running: ${FPM_SERVICE}"; PASS=$((PASS+1))
else
    fail "No PHP-FPM service running"; FAIL=$((FAIL+1))
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo "?")
pass "PHP version: ${PHP_VERSION}"; PASS=$((PASS+1))

# ── 4. Cron ──
echo ""
echo -e "${YELLOW}── Cron ──${NC}"

WS_USER=$(detect_ws_user)

CRON_FOUND=false
CRON_USER=""
for user in "$WS_USER" "root"; do
    if sudo crontab -u "$user" -l 2>/dev/null | grep -q "dns:auto-sync"; then
        pass "Cron auto-sync found (user: ${user})"; PASS=$((PASS+1))
        CRON_FOUND=true; CRON_USER="$user"; break
    fi
done
if ! $CRON_FOUND; then
    CRON_JOB="*/5 * * * * cd ${APP_DIR} && php artisan dns:auto-sync >> storage/logs/dns-sync.log 2>&1"
    if $FIX_MODE; then
        CURRENT=$(sudo crontab -u "$WS_USER" -l 2>/dev/null || true)
        { echo "$CURRENT"; echo "$CRON_JOB"; } | sudo crontab -u "$WS_USER" - 2>/dev/null
        pass "Cron auto-sync added for user ${WS_USER} (fixed)"; PASS=$((PASS+1)); FIXED=$((FIXED+1))
    else
        fail "Cron auto-sync not configured — sudo bash check.sh --fix"; FAIL=$((FAIL+1))
    fi
fi

# ── 5. Database & migrations ──
echo ""
echo -e "${YELLOW}── Database ──${NC}"

if [[ -f "$DB_FILE" ]]; then
    if php -r "try { new PDO('sqlite:${DB_FILE}'); echo 'ok'; } catch(Exception \$e) { echo 'fail'; }" 2>/dev/null | grep -q ok; then
        pass "SQLite database readable"; PASS=$((PASS+1))
    else
        fail "Cannot read SQLite database — check permissions"; FAIL=$((FAIL+1))
    fi
    if php "${APP_DIR}/artisan" migrate:status 2>/dev/null | grep -qi "Ran"; then
        pass "Migrations have been run"; PASS=$((PASS+1))
    else
        if $FIX_MODE && php "${APP_DIR}/artisan" migrate --force --quiet 2>/dev/null; then
            pass "Migrations run (fixed)"; PASS=$((PASS+1)); FIXED=$((FIXED+1))
        else
            fail "Migrations not run — php artisan migrate --force"; FAIL=$((FAIL+1))
        fi
    fi
fi

# ── 6. Ports ──
echo ""
echo -e "${YELLOW}── Ports ──${NC}"

if command -v ss &>/dev/null; then
    if ss -tlnp | grep -q ':80 '; then
        pass "Port 80 is listening"; PASS=$((PASS+1))
    else
        if $FIX_MODE; then
            warn "Port 80 not listening — restart web server: sudo systemctl restart ${WS}"; WARN=$((WARN+1))
        else
            fail "Port 80 not listening"; FAIL=$((FAIL+1))
        fi
    fi
    if ss -tlnp | grep -q ':443 '; then
        pass "Port 443 is listening"; PASS=$((PASS+1))
    else
        warn "Port 443 not listening — SSL mungkin belum di-setup (bash add-domain.sh)"; WARN=$((WARN+1))
    fi
fi

# ── Summary ──
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "  ${GREEN}Pass: ${PASS}${NC}  ${RED}Fail: ${FAIL}${NC}  ${YELLOW}Warn: ${WARN}${NC}  ${CYAN}Fixed: ${FIXED}${NC}"
if [[ $FAIL -eq 0 ]]; then
    echo -e "  ${GREEN}✅  Semua baik!${NC}"
else
    echo -e "  ${YELLOW}⚠️  Ada ${FAIL} masalah. Jalanin: sudo bash check.sh --fix${NC}"
fi
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

exit $FAIL
