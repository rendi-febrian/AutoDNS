#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
pass() { echo -e "  ${GREEN}✓${NC} $1"; }
fail() { echo -e "  ${RED}✗${NC} $1"; }
warn() { echo -e "  ${YELLOW}⚠${NC} $1"; }
info() { echo -e "  ${CYAN}→${NC} $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_NAME="autodns"
PASS=0; FAIL=0; WARN=0

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN} Auto DNS Domain — Health Check${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "  Direktori: ${APP_DIR}"
echo ""

# ── 1. Directory & permissions ──
echo -e "${YELLOW}── Files & Permissions ──${NC}"

if [[ -f "${APP_DIR}/artisan" ]]; then
    pass "artisan found"; ((PASS++))
else
    fail "artisan missing — not a Laravel project"; ((FAIL++))
fi

if [[ -f "${APP_DIR}/.env" ]]; then
    pass ".env exists"; ((PASS++))
    if grep -q "APP_KEY=" "${APP_DIR}/.env" && ! grep -q "APP_KEY=$" "${APP_DIR}/.env"; then
        pass "APP_KEY is set"; ((PASS++))
    else
        fail "APP_KEY not set — run: php artisan key:generate"; ((FAIL++))
    fi
else
    fail ".env missing — cp .env.example .env && php artisan key:generate"; ((FAIL++))
fi

DB_FILE="${APP_DIR}/database/database.sqlite"
if [[ -f "$DB_FILE" ]]; then
    pass "SQLite database exists"; ((PASS++))
else
    fail "database.sqlite missing"; ((FAIL++))
fi

for dir in storage bootstrap/cache database; do
    if [[ -w "${APP_DIR}/${dir}" ]]; then
        pass "${dir} is writable"; ((PASS++))
    else
        fail "${dir} not writable — chown -R www-data:www-data ${dir}"; ((FAIL++))
    fi
done

# ── 2. Web server ──
echo ""
echo -e "${YELLOW}── Web Server ──${NC}"

detect_ws() {
    if command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then echo "nginx"
    elif command -v apache2 &>/dev/null && systemctl is-active --quiet apache2 2>/dev/null; then echo "apache"
    else echo "unknown"; fi
}

WS=$(detect_ws)
case "$WS" in
    nginx)
        pass "Nginx is running"; ((PASS++))
        if [[ -f /etc/nginx/sites-enabled/${APP_NAME} ]]; then
            pass "Nginx vhost enabled: /etc/nginx/sites-enabled/${APP_NAME}"; ((PASS++))
        elif [[ -f /etc/nginx/sites-available/${APP_NAME} ]]; then
            warn "Vhost exists but not enabled — ln -sf ../sites-available/${APP_NAME} /etc/nginx/sites-enabled/"; ((WARN++))
        else
            fail "Nginx vhost not found — run install.sh"; ((FAIL++))
        fi
        ;;
    apache)
        pass "Apache is running"; ((PASS++))
        if [[ -f /etc/apache2/sites-enabled/${APP_NAME}.conf ]]; then
            pass "Apache vhost enabled: /etc/apache2/sites-enabled/${APP_NAME}.conf"; ((PASS++))
        elif [[ -f /etc/apache2/sites-available/${APP_NAME}.conf ]]; then
            warn "Vhost exists but not enabled — a2ensite ${APP_NAME}.conf"; ((WARN++))
        else
            fail "Apache vhost not found — run install.sh"; ((FAIL++))
        fi
        if apache2ctl -M 2>/dev/null | grep -q 'ssl_module'; then
            pass "Apache SSL module enabled"; ((PASS++))
        else
            warn "SSL module not enabled — a2enmod ssl"; ((WARN++))
        fi
        ;;
    *)
        fail "No web server detected (nginx/apache)"; ((FAIL++))
        ;;
esac

# ── 3. PHP-FPM ──
echo ""
echo -e "${YELLOW}── PHP-FPM ──${NC}"

FPM_SERVICE=$(systemctl list-units --type=service --state=running 2>/dev/null | grep 'php.*fpm' | head -1 | awk '{print $1}' || true)
if [[ -n "$FPM_SERVICE" ]]; then
    pass "PHP-FPM running: ${FPM_SERVICE}"; ((PASS++))
else
    fail "No PHP-FPM service running"; ((FAIL++))
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo "?")
pass "PHP version: ${PHP_VERSION}"; ((PASS++))

# ── 4. Cron ──
echo ""
echo -e "${YELLOW}── Cron ──${NC}"

detect_ws_user() {
    if [[ -f /etc/apache2/envvars ]]; then
        local u; u=$(grep -oP '^export APACHE_RUN_USER=\K.+' /etc/apache2/envvars 2>/dev/null | tr -d '"')
        [[ -n "$u" ]] && echo "$u" && return 0
    fi
    if command -v ps &>/dev/null; then
        local u; u=$(ps -o user= -C apache2 2>/dev/null | head -1)
        [[ -n "$u" ]] && echo "$u" && return 0
        u=$(ps -o user= -C nginx 2>/dev/null | head -1)
        [[ -n "$u" ]] && echo "$u" && return 0
    fi
    echo "www-data"
}
WS_USER=$(detect_ws_user)

CRON_FOUND=false
for user in "$WS_USER" "root"; do
    if sudo crontab -u "$user" -l 2>/dev/null | grep -q "dns:auto-sync"; then
        pass "Cron auto-sync found (user: ${user})"; ((PASS++))
        CRON_FOUND=true
        break
    fi
done
if ! $CRON_FOUND; then
    fail "Cron auto-sync not configured — tambahkan: */5 * * * * cd ${APP_DIR} && php artisan dns:auto-sync >> storage/logs/dns-sync.log 2>&1"; ((FAIL++))
fi

# ── 5. Database & migrations ──
echo ""
echo -e "${YELLOW}── Database ──${NC}"

if [[ -f "$DB_FILE" ]]; then
    if php -r "try { new PDO('sqlite:${DB_FILE}'); echo 'ok'; } catch(Exception \$e) { echo 'fail'; }" 2>/dev/null | grep -q ok; then
        pass "SQLite database readable"; ((PASS++))
    else
        fail "Cannot read SQLite database — check permissions"; ((FAIL++))
    fi
    if php "${APP_DIR}/artisan" migrate:status 2>/dev/null | grep -qi "Ran"; then
        pass "Migrations have been run"; ((PASS++))
    else
        fail "Migrations not run — php artisan migrate --force"; ((FAIL++))
    fi
fi

# ── 6. Services ──
echo ""
echo -e "${YELLOW}── Ports ──${NC}"

if command -v ss &>/dev/null; then
    if ss -tlnp | grep -q ':80 '; then
        pass "Port 80 is listening"; ((PASS++))
    else
        fail "Port 80 not listening"; ((FAIL++))
    fi
    if ss -tlnp | grep -q ':443 '; then
        pass "Port 443 is listening"; ((PASS++))
    else
        warn "Port 443 not listening — SSL mungkin belum di-setup"; ((WARN++))
    fi
fi

# ── Summary ──
echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "  ${GREEN}Pass: ${PASS}${NC}  ${RED}Fail: ${FAIL}${NC}  ${YELLOW}Warn: ${WARN}${NC}"
if [[ $FAIL -eq 0 ]]; then
    echo -e "  ${GREEN}✅  Semua baik!${NC}"
else
    echo -e "  ${YELLOW}⚠️  Ada ${FAIL} masalah yang perlu diperbaiki.${NC}"
fi
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

exit $FAIL
