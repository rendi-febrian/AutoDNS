#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"

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

# ── Web server detection ──
detect_ws() {
    if command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then
        echo "nginx"
    elif command -v apache2 &>/dev/null && systemctl is-active --quiet apache2 2>/dev/null; then
        echo "apache"
    else
        echo "unknown"
    fi
}
WS=$(detect_ws)

# ── Cloudflare IP check ──
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

# ── Usage ──
if [[ $# -lt 1 ]]; then
    echo -e "Usage: ${CYAN}bash add-domain.sh${NC} ${YELLOW}<domain>${NC}"
    exit 1
fi

DOMAIN="$1"
echo ""
info "Domain: ${DOMAIN}"

if [[ ! -f "${APP_DIR}/artisan" ]]; then
    err "artisan not found. Run from the AutoDNS directory."
    exit 1
fi

# ── Server IP ──
info "Detecting server public IP..."
SERVER_IP=$(curl -4 -s --max-time 10 https://ipv4.icanhazip.com 2>/dev/null || curl -4 -s --max-time 10 https://api.ipify.org 2>/dev/null || true)
if [[ -z "$SERVER_IP" ]]; then
    err "Could not detect server public IP."
    exit 1
fi
ok "Server IP: ${SERVER_IP}"

# ── DNS check ──
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
    exit 1
fi
ok "Resolved:  ${DOMAIN} → ${RESOLVED}"

if [[ "$RESOLVED" != "$SERVER_IP" ]]; then
    if is_cloudflare_ip "$RESOLVED"; then
        warn "Domain proxied by Cloudflare (${RESOLVED}). Make sure A record points to ${SERVER_IP}."
    else
        err "Domain resolves to ${RESOLVED}, not ${SERVER_IP}. Update A record first."
        exit 1
    fi
fi
ok "Domain check passed!"

# ── Update server_name in vhost ──
info "Setting server_name in vhost..."
case "$WS" in
    nginx)
        if [[ -f /etc/nginx/sites-available/autodns ]]; then
            if grep -q "server_name localhost;" /etc/nginx/sites-available/autodns; then
                sudo sed -i "s/server_name localhost;/server_name ${DOMAIN};/" /etc/nginx/sites-available/autodns
            elif ! grep -q "server_name.*\b${DOMAIN}\b" /etc/nginx/sites-available/autodns; then
                sudo sed -i "s/server_name\(.*\);/server_name\1 ${DOMAIN};/" /etc/nginx/sites-available/autodns
            fi
            sudo systemctl reload nginx 2>/dev/null || true
            ok "Nginx vhost updated"
        else
            err "Nginx vhost /etc/nginx/sites-available/autodns not found. Run install.sh first."
            exit 1
        fi
        ;;
    apache)
        if [[ -f /etc/apache2/sites-available/autodns.conf ]]; then
            if grep -q "ServerName localhost" /etc/apache2/sites-available/autodns.conf; then
                sudo sed -i "s/ServerName localhost/ServerName ${DOMAIN}/" /etc/apache2/sites-available/autodns.conf
            elif ! grep -q "ServerName.*\b${DOMAIN}\b" /etc/apache2/sites-available/autodns.conf; then
                sudo sed -i "/ServerAdmin/a\    ServerName ${DOMAIN}" /etc/apache2/sites-available/autodns.conf
            fi
            sudo systemctl reload apache2 2>/dev/null || true
            ok "Apache vhost updated"
        else
            err "Apache vhost /etc/apache2/sites-available/autodns.conf not found. Run install.sh first."
            exit 1
        fi
        ;;
    *)
        err "No supported web server detected (nginx/apache)."
        exit 1
        ;;
esac

# ── Wait for web server to be ready ──
sleep 1

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
fi

if ! command -v certbot &>/dev/null; then
    warn "certbot not available. Install manually: sudo apt install certbot"
    warn "Skipping SSL — domain tracking will continue."
else
    LE_DIR="/etc/letsencrypt/live/${DOMAIN}"
    CERTBOT_OK=false

    if [[ -f "${LE_DIR}/fullchain.pem" ]]; then
        ok "SSL certificate already exists for ${DOMAIN}"
        CERTBOT_OK=true
    else
        info "Requesting SSL certificate for ${DOMAIN}..."
        if sudo certbot certonly --webroot -w "${APP_DIR}/public" -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email 2>&1; then
            CERTBOT_OK=true
            ok "SSL certificate obtained for ${DOMAIN}"
        else
            warn "certbot failed. Run manually: sudo certbot certonly --webroot -w ${APP_DIR}/public -d ${DOMAIN}"
            warn "Skipping SSL injection — domain tracking will continue."
        fi
    fi

    # ── Inject SSL ke vhost port 443 ──
    if [[ "$CERTBOT_OK" == true ]]; then
        info "Injecting SSL config into vhost (port 443)..."

        case "$WS" in
            nginx)
                if grep -q "listen 443 ssl" /etc/nginx/sites-available/autodns 2>/dev/null; then
                    ok "SSL config already present in Nginx vhost"
                else
                    # Add SSL config after server_name line
                    sudo sed -i "/server_name ${DOMAIN};/a\    listen 443 ssl;\n    ssl_certificate ${LE_DIR}/fullchain.pem;\n    ssl_certificate_key ${LE_DIR}/privkey.pem;" /etc/nginx/sites-available/autodns
                    ok "SSL injected into Nginx vhost"
                fi
                ;;
            apache)
                if grep -q "SSLEngine" /etc/apache2/sites-available/autodns.conf 2>/dev/null; then
                    ok "SSL config already present in Apache vhost"
                else
                    sudo a2enmod ssl >/dev/null 2>&1 || true
                    # Change vhost to listen on both 80 and 443
                    sudo sed -i "s/<VirtualHost \*:80>/<VirtualHost *:80 *:443>/" /etc/apache2/sites-available/autodns.conf
                    # Add SSL directives after ServerName
                    sudo sed -i "/ServerName ${DOMAIN}/a\    SSLEngine on\n    SSLCertificateFile ${LE_DIR}/fullchain.pem\n    SSLCertificateKeyFile ${LE_DIR}/privkey.pem" /etc/apache2/sites-available/autodns.conf
                    ok "SSL injected into Apache vhost"
                fi

                if ! grep -q "^Listen 443" /etc/apache2/ports.conf 2>/dev/null; then
                    echo "Listen 443" | sudo tee -a /etc/apache2/ports.conf >/dev/null
                fi
                ;;
        esac

        # Reload web server
        sudo systemctl reload "$WS" 2>/dev/null || true
        ok "Web server reloaded with SSL"

        # Auto-renew cron
        if ! systemctl is-active --quiet certbot.timer 2>/dev/null && ! systemctl is-active --quiet certbot-renew.timer 2>/dev/null; then
            if ! sudo crontab -l 2>/dev/null | grep -q "certbot renew"; then
                (sudo crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet") | sudo crontab -
                ok "certbot auto-renew cron added (daily 3 AM)"
            fi
        fi
    fi
fi

# ── Track domain via Artisan ──
info "Adding domain to tracked domains..."
cd "$APP_DIR"
sudo chown -R "${WS_USER}:${WS_USER}" storage database bootstrap/cache 2>/dev/null || true
sudo -u "$WS_USER" php artisan domain:track "$DOMAIN" --ip="$SERVER_IP"

echo ""
ok "Done! ${DOMAIN} is now tracked with SSL on port 443."
