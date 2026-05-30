#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"

# ── Cek apakah IP milik Cloudflare ──
is_cloudflare_ip() {
    local ip="$1"
    # Official Cloudflare IPv4 ranges: https://www.cloudflare.com/ips-v4
    # 173.245.48.0/20, 103.21.244.0/22, 103.22.200.0/22, 103.31.4.0/22,
    # 141.101.64.0/18, 108.162.192.0/18, 190.93.240.0/20, 188.114.96.0/20,
    # 197.234.240.0/22, 198.41.128.0/17, 162.158.0.0/15, 104.16.0.0/13,
    # 104.24.0.0/14, 172.64.0.0/13, 131.0.72.0/22
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
    echo "  Script checks that the domain already resolves to this server's IP."
    echo ""
    echo "  Examples:"
    echo "    bash add-domain.sh example.com"
    echo "    bash add-domain.sh sub.example.com"
    echo "    bash add-domain.sh example.co.id"
    exit 1
fi

DOMAIN="$1"

echo ""
info "Domain: ${DOMAIN}"
info "Working dir: ${APP_DIR}"

# ── Check artisan exists ──
if [[ ! -f "${APP_DIR}/artisan" ]]; then
    err "artisan not found. Run this script from the AutoDNS installation directory."
    exit 1
fi

# ── Detect server public IP ──
info "Detecting server public IP..."
SERVER_IP=$(curl -4 -s --max-time 10 https://ipv4.icanhazip.com 2>/dev/null || true)
if [[ -z "$SERVER_IP" ]]; then
    SERVER_IP=$(curl -4 -s --max-time 10 https://api.ipify.org 2>/dev/null || true)
fi
if [[ -z "$SERVER_IP" ]]; then
    err "Could not detect server public IP. Check internet connection."
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
    echo ""
    echo -e "  ${YELLOW}Make sure the domain's A record points to ${SERVER_IP} first.${NC}"
    echo -e "  ${YELLOW}Then run this script again.${NC}"
    exit 1
fi

ok "Resolved:  ${DOMAIN} → ${RESOLVED}"

# ── Bandingkan IP ──
if [[ "$RESOLVED" != "$SERVER_IP" ]]; then
    if is_cloudflare_ip "$RESOLVED"; then
        warn "Domain is proxied by Cloudflare (${RESOLVED})."
        warn "Skipping IP match — make sure the A record in Cloudflare points to ${SERVER_IP}."
    else
        err "Domain resolves to ${RESOLVED}, not server IP ${SERVER_IP}."
        echo ""
        echo -e "  ${YELLOW}Update the A record for ${DOMAIN} to point to ${SERVER_IP},${NC}"
        echo -e "  ${YELLOW}wait for DNS propagation, then run this script again.${NC}"
        exit 1
    fi
fi

ok "Domain check passed!"

# ── SSL via Certbot ──
info "Setting up SSL certificate..."
INSTALLED_CERTBOT=false
if ! command -v certbot &>/dev/null; then
    info "Installing certbot..."
    if command -v snap &>/dev/null; then
        sudo snap install certbot --classic 2>/dev/null && INSTALLED_CERTBOT=true
    fi
    if ! command -v certbot &>/dev/null; then
        if command -v apt &>/dev/null; then
            sudo apt install -y certbot 2>/dev/null && INSTALLED_CERTBOT=true
        elif command -v dnf &>/dev/null; then
            sudo dnf install -y certbot 2>/dev/null && INSTALLED_CERTBOT=true
        fi
    fi
    if command -v certbot &>/dev/null; then
        ok "certbot installed"
    else
        warn "Could not install certbot. Install manually: sudo apt install certbot"
    fi
else
    ok "certbot already installed"
fi

if command -v certbot &>/dev/null; then
    # Deteksi web server
    WS_PLUGIN=""
    if command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then
        WS_PLUGIN="nginx"
    elif command -v apache2 &>/dev/null && systemctl is-active --quiet apache2 2>/dev/null; then
        WS_PLUGIN="apache"
    fi

    if [[ -n "$WS_PLUGIN" ]]; then
        if certbot certificates 2>/dev/null | grep -q "Domains:.*\b${DOMAIN}\b"; then
            ok "SSL certificate already exists for ${DOMAIN}"
        else
            info "Running certbot --${WS_PLUGIN} for ${DOMAIN}..."
            sudo certbot --"${WS_PLUGIN}" -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email --redirect 2>&1 || {
                warn "certbot failed for ${DOMAIN}. Run manually: sudo certbot --${WS_PLUGIN} -d ${DOMAIN}"
            }
        fi
    else
        warn "No active web server (nginx/apache) detected. Run certbot manually."
    fi

    # Auto-renew cron (fallback jika systemd timer tidak aktif)
    if ! systemctl is-active --quiet certbot.timer 2>/dev/null && ! systemctl is-active --quiet certbot-renew.timer 2>/dev/null; then
        if ! crontab -l 2>/dev/null | grep -q "certbot renew"; then
            (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet") | crontab -
            ok "certbot auto-renew cron added (daily 3 AM)"
        fi
    fi
fi

# ── Add via Artisan ──
info "Adding domain to tracked domains..."
cd "$APP_DIR"
sudo chown -R www-data:www-data storage database bootstrap/cache 2>/dev/null || true
sudo -u www-data php artisan domain:track "$DOMAIN" --ip="$SERVER_IP"

echo ""
ok "Done! ${DOMAIN} is now tracked, SSL-enabled, and will be auto-synced."
