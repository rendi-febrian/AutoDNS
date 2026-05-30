#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"

# Deteksi user web server
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

# ── Cek apakah IP milik Cloudflare ──
is_cloudflare_ip() {
    local ip="$1"
    [[ "$ip" =~ ^173\.245\.(4[89]|5[0-9]|6[0-3])\. ]] && return 0
    [[ "$ip" =~ ^103\.21\.24[4-7]\. ]] && return 0
    [[ "$ip" =~ ^103\.22\.20[0-3]\. ]] && return 0
    [[ "$ip" =~ ^103\.31\.[4-7]\. ]] && return 0
    [[ "$ip" =~ ^141\.101\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\. ]] && return 0
    [[ "$ip" =~ ^108\.162\.(19[2-9]|2[0-4][0-9]|25[0-5])\. ]] && return 0
    [[ "$ip" =~ ^190\.93\.(24[0-9]|25[0-5])\. ]] && return 0
    [[ "$ip" =~ ^188\.114\.(9[6-9]|10[0-9]|11[0-1])\. ]] && return 0
    [[ "$ip" =~ ^197\.234\.24[0-3]\. ]] && return 0
    [[ "$ip" =~ ^198\.41\.(12[8-9]|1[3-9][0-9]|2[0-4][0-9]|25[0-5])\. ]] && return 0
    [[ "$ip" =~ ^162\.15[89]\. ]] && return 0
    [[ "$ip" =~ ^104\.(1[6-9]|2[0-3])\. ]] && return 0
    [[ "$ip" =~ ^104\.(2[4-7])\. ]] && return 0
    [[ "$ip" =~ ^172\.(6[4-9]|7[01])\. ]] && return 0
    [[ "$ip" =~ ^131\.0\.(7[2-5])\. ]] && return 0
    return 1
}

# ── Domain argument ──
if [[ $# -lt 1 ]]; then
    echo -e "Usage: ${CYAN}bash add-domain.sh${NC} ${YELLOW}<domain>${NC}"
    echo ""
    echo "  Add a domain to AutoDNS tracked domains."
    echo "  Cek A record, update vhost ServerName, dan tambah ke tracking."
    echo ""
    echo "  Examples:"
    echo "    bash add-domain.sh example.com"
    echo "    bash add-domain.sh example.co.id"
    exit 1
fi

DOMAIN="$1"

echo ""
info "Domain: ${DOMAIN}"

# ── Check artisan exists ──
if [[ ! -f "${APP_DIR}/artisan" ]]; then
    err "artisan not found. Run this script from the AutoDNS directory."
    exit 1
fi

# ── Detect server public IP ──
info "Detecting server public IP..."
SERVER_IP=$(curl -4 -s --max-time 10 https://ipv4.icanhazip.com 2>/dev/null || true)
if [[ -z "$SERVER_IP" ]]; then
    SERVER_IP=$(curl -4 -s --max-time 10 https://api.ipify.org 2>/dev/null || true)
fi
if [[ -z "$SERVER_IP" ]]; then
    err "Could not detect server public IP."
    exit 1
fi
ok "Server IP: ${SERVER_IP}"

# ── Resolve domain A record ──
info "Resolving ${DOMAIN}..."
RESOLVED=""
if command -v dig &>/dev/null; then
    RESOLVED=$(dig +short A "$DOMAIN" 2>/dev/null | head -1 || true)
elif command -v host &>/dev/null; then
    RESOLVED=$(host -t A "$DOMAIN" 2>/dev/null | awk '/has address/ {print $NF; exit}' || true)
else
    RESOLVED=$(php -r "echo gethostbyname('$DOMAIN');" 2>/dev/null || true)
fi

if [[ -z "$RESOLVED" || "$RESOLVED" == "$DOMAIN" ]]; then
    err "${DOMAIN} does not resolve to any IP."
    echo -e "  ${YELLOW}Set A record → ${SERVER_IP} first, then rerun.${NC}"
    exit 1
fi

ok "Resolved:  ${DOMAIN} → ${RESOLVED}"

# ── Bandingkan IP ──
if [[ "$RESOLVED" != "$SERVER_IP" ]]; then
    if is_cloudflare_ip "$RESOLVED"; then
        warn "Domain proxied by Cloudflare (${RESOLVED}). Make sure A record points to ${SERVER_IP}."
    else
        err "Domain resolves to ${RESOLVED}, not server IP ${SERVER_IP}."
        echo -e "  ${YELLOW}Update A record to ${SERVER_IP} first.${NC}"
        exit 1
    fi
fi

ok "Domain check passed!"

# ── Update vhost server_name ──
if command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null && [[ -f /etc/nginx/sites-available/autodns ]]; then
    if grep -q "server_name localhost;" /etc/nginx/sites-available/autodns; then
        sudo sed -i "s/server_name localhost;/server_name ${DOMAIN};/" /etc/nginx/sites-available/autodns
    elif ! grep -q "server_name.*\b${DOMAIN}\b" /etc/nginx/sites-available/autodns; then
        sudo sed -i "s/server_name\(.*\);/server_name\1 ${DOMAIN};/" /etc/nginx/sites-available/autodns
    fi
    sudo systemctl reload nginx 2>/dev/null || true
    ok "Nginx vhost updated with server_name ${DOMAIN}"

elif command -v apache2 &>/dev/null && systemctl is-active --quiet apache2 2>/dev/null && [[ -f /etc/apache2/sites-available/autodns.conf ]]; then
    if grep -q "ServerName localhost" /etc/apache2/sites-available/autodns.conf; then
        sudo sed -i "s/ServerName localhost/ServerName ${DOMAIN}/" /etc/apache2/sites-available/autodns.conf
    elif ! grep -q "ServerName.*\b${DOMAIN}\b" /etc/apache2/sites-available/autodns.conf; then
        if grep -q "ServerAdmin" /etc/apache2/sites-available/autodns.conf && ! grep -q "ServerName" /etc/apache2/sites-available/autodns.conf; then
            sudo sed -i "/ServerAdmin/a\    ServerName ${DOMAIN}" /etc/apache2/sites-available/autodns.conf
        fi
    fi
    sudo systemctl reload apache2 2>/dev/null || true
    ok "Apache vhost updated with ServerName ${DOMAIN}"
fi

# ── Add via Artisan ──
info "Adding domain to tracked domains..."
cd "$APP_DIR"
sudo chown -R "${WS_USER}:${WS_USER}" storage database bootstrap/cache 2>/dev/null || true
sudo -u "$WS_USER" php artisan domain:track "$DOMAIN" --ip="$SERVER_IP"

echo ""
ok "Done! ${DOMAIN} is now tracked and will be auto-synced."
