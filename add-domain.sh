#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"

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
    # fallback: use php/ping
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

# ── Compare ──
if [[ "$RESOLVED" != "$SERVER_IP" ]]; then
    err "Domain resolves to ${RESOLVED}, not server IP ${SERVER_IP}."
    echo ""
    echo -e "  ${YELLOW}Update the A record for ${DOMAIN} to point to ${SERVER_IP},${NC}"
    echo -e "  ${YELLOW}wait for DNS propagation, then run this script again.${NC}"
    exit 1
fi

ok "Domain points to this server!"

# ── Add via Artisan ──
info "Adding domain to tracked domains..."
cd "$APP_DIR"
php artisan domain:track "$DOMAIN" --ip="$SERVER_IP"

echo ""
ok "Done! ${DOMAIN} is now tracked and will be auto-synced."
