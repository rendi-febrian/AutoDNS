#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_PORT="${APP_PORT:-26298}"

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
    # Deteksi web server (cuma buat keperluan reload)
    WS_PLUGIN=""
    if command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null; then
        WS_PLUGIN="nginx"
    elif command -v apache2 &>/dev/null && systemctl is-active --quiet apache2 2>/dev/null; then
        WS_PLUGIN="apache"
    fi

    # Set server_name di HTTP vhost + tambah listen 80 buat certbot challenge
    if [[ "$WS_PLUGIN" == "nginx" ]] && [[ -f /etc/nginx/sites-available/autodns ]]; then
        if grep -q "server_name _;" /etc/nginx/sites-available/autodns; then
            sudo sed -i "s/server_name _;/server_name ${DOMAIN};/" /etc/nginx/sites-available/autodns
        elif ! grep -q "server_name.*\b${DOMAIN}\b" /etc/nginx/sites-available/autodns; then
            sudo sed -i "s/server_name\(.*\);/server_name\1 ${DOMAIN};/" /etc/nginx/sites-available/autodns
        fi
        if ! grep -q "listen 80;" /etc/nginx/sites-available/autodns; then
            sudo sed -i "s/listen ${APP_PORT};/listen ${APP_PORT};\n    listen 80;\n    listen [::]:80;/" /etc/nginx/sites-available/autodns
        fi
    elif [[ "$WS_PLUGIN" == "apache" ]] && [[ -f /etc/apache2/sites-available/autodns.conf ]]; then
        if ! grep -q "ServerName.*\b${DOMAIN}\b" /etc/apache2/sites-available/autodns.conf; then
            if grep -q "ServerAdmin" /etc/apache2/sites-available/autodns.conf && ! grep -q "ServerName" /etc/apache2/sites-available/autodns.conf; then
                sudo sed -i "/ServerAdmin/a\    ServerName ${DOMAIN}" /etc/apache2/sites-available/autodns.conf
            fi
        fi
        if ! grep -q "^Listen 80" /etc/apache2/ports.conf 2>/dev/null; then
            echo "Listen 80" | sudo tee -a /etc/apache2/ports.conf >/dev/null
        fi
    fi

    sudo systemctl reload "$WS_PLUGIN" 2>/dev/null || true

    # Certbot: pakai certonly --webroot (gak sentuh vhost config)
    LE_DIR="/etc/letsencrypt/live/${DOMAIN}"
    if [[ -f "${LE_DIR}/fullchain.pem" ]]; then
        ok "SSL certificate already exists for ${DOMAIN}"
        CERTBOT_OK=true
    else
        info "Getting SSL certificate for ${DOMAIN} via webroot..."
        if sudo certbot certonly --webroot -w "${APP_DIR}/public" -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email 2>&1; then
            CERTBOT_OK=true
            ok "SSL certificate installed for ${DOMAIN}"
        else
            warn "certbot failed. Run manually: sudo certbot certonly --webroot -w ${APP_DIR}/public -d ${DOMAIN}"
        fi
    fi

    # Pasang SSL ke vhost (port 26298)
    if [[ "$CERTBOT_OK" == true ]]; then
        if [[ "$WS_PLUGIN" == "nginx" ]]; then
            # Tambah listen 443 ssl + sertifikat ke server block
            if ! grep -q "listen 443 ssl;" /etc/nginx/sites-available/autodns; then
                sudo sed -i "/listen 80;/a\    listen 443 ssl;" /etc/nginx/sites-available/autodns
            fi
            if ! grep -q "ssl_certificate_key.*${DOMAIN}" /etc/nginx/sites-available/autodns; then
                sudo sed -i "/server_name.*${DOMAIN}/a\    ssl_certificate ${LE_DIR}/fullchain.pem;\n    ssl_certificate_key ${LE_DIR}/privkey.pem;" /etc/nginx/sites-available/autodns
            fi
            ok "Nginx SSL config added"
        elif [[ "$WS_PLUGIN" == "apache" ]]; then
            # Aktifkan mod ssl & buat SSL virtualhost
            sudo a2enmod ssl >/dev/null 2>&1 || true
            if ! grep -q "SSLEngine" /etc/apache2/sites-available/autodns.conf; then
                sudo sed -i "/ServerName ${DOMAIN}/a\    SSLEngine on\n    SSLCertificateFile ${LE_DIR}/fullchain.pem\n    SSLCertificateKeyFile ${LE_DIR}/privkey.pem" /etc/apache2/sites-available/autodns.conf
            fi
            if ! grep -q "<VirtualHost \*:443>" /etc/apache2/sites-available/autodns.conf; then
                # Duplikat vhost buat port 443
                sudo sed -i "s/<VirtualHost \*:${APP_PORT}>/<VirtualHost *:${APP_PORT}>\n<VirtualHost *:443>\n    ServerName ${DOMAIN}\n    SSLEngine on\n    SSLCertificateFile ${LE_DIR}/fullchain.pem\n    SSLCertificateKeyFile ${LE_DIR}/privkey.pem\n    <IfModule mod_rewrite.c>\n        RewriteEngine On\n        RewriteCond %{HTTPS} off\n        RewriteRule ^ https:\/\/%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n    <\/IfModule>\n<\/VirtualHost>/" /etc/apache2/sites-available/autodns.conf
            fi
            if ! grep -q "^Listen 443" /etc/apache2/ports.conf 2>/dev/null; then
                echo "Listen 443" | sudo tee -a /etc/apache2/ports.conf >/dev/null
            fi
            ok "Apache SSL config added"
        fi

        sudo systemctl reload "$WS_PLUGIN" 2>/dev/null || true
        ok "Web server reloaded"
    fi

    # Auto-renew cron
    if ! systemctl is-active --quiet certbot.timer 2>/dev/null && ! systemctl is-active --quiet certbot-renew.timer 2>/dev/null; then
        if ! sudo crontab -l 2>/dev/null | grep -q "certbot renew"; then
            (sudo crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet") | sudo crontab -
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
