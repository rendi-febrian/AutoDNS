#!/bin/bash
set -euo pipefail

# ─────────────────────────────────────────────────────
# AutoDNS Dashboard — Installer
# ─────────────────────────────────────────────────────

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1" >&2; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_NAME="autodns"
APP_PORT="${APP_PORT:-26298}"
APP_USER="${APP_USER:-www-data}"

# ─────────────────────────────────────────────────────
# 1. Root check
# ─────────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || { err "Jalankan dengan sudo: sudo bash install.sh"; exit 1; }

# ─────────────────────────────────────────────────────
# 2. Detect OS
# ─────────────────────────────────────────────────────
info "Mendeteksi sistem operasi..."
OS=""
PKG=""
PHP_FPM_SERVICE=""
PHP_VERSION="8.3"
INSTALL_PHP_FROM_REPO=false

if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    case "$ID" in
        ubuntu|debian)
            OS="$ID"
            PKG="apt"
            PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
            [[ "$ID" == "ubuntu" ]] && INSTALL_PHP_FROM_REPO=true
            ;;
        fedora)
            OS="fedora"
            PKG="dnf"
            PHP_FPM_SERVICE="php-fpm"
            PHP_VERSION=""
            ;;
        centos|rhel)
            OS="$ID"
            PKG="dnf"
            PHP_FPM_SERVICE="php-fpm"
            PHP_VERSION=""
            ;;
        *)
            err "OS tidak didukung: $ID"
            exit 1
            ;;
    esac
else
    err "Tidak bisa mendeteksi OS."
    exit 1
fi
ok "OS: $ID"

# ─────────────────────────────────────────────────────
# 3. Install prerequisites
# ─────────────────────────────────────────────────────
install_pkgs() {
    case "$PKG" in
        apt)
            apt-get update -qq
            DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "$@"
            ;;
        dnf)
            dnf install -y -q "$@"
            ;;
    esac
}

# --- PHP ---
NEED_PHP_FPM=false
info "Memeriksa PHP ${PHP_VERSION}..."
if command -v php &>/dev/null; then
    INSTALLED_PHP=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    ok "PHP $INSTALLED_PHP sudah terinstall"
    PHP_VERSION="$INSTALLED_PHP"
    PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
    # Cek PHP-FPM terpisah
    if ! command -v "php-fpm${PHP_VERSION}" &>/dev/null && \
       ! systemctl is-enabled --quiet "$PHP_FPM_SERVICE" 2>/dev/null; then
        NEED_PHP_FPM=true
    fi
else
    NEED_PHP_FPM=true
    warn "PHP ${PHP_VERSION} belum terinstall. Menginstall..."
    if $INSTALL_PHP_FROM_REPO; then
        install_pkgs software-properties-common
        add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
        apt-get update -qq
    fi
    install_pkgs "php${PHP_VERSION}" \
        "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-curl" \
        "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
        "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-gd"
fi

if $NEED_PHP_FPM; then
    info "Menginstall PHP-FPM ${PHP_VERSION}..."
    install_pkgs "${PHP_FPM_SERVICE}"
fi
ok "PHP ${PHP_VERSION} + FPM siap"

# --- Composer ---
info "Memeriksa Composer..."
if ! command -v composer &>/dev/null; then
    warn "Composer belum terinstall. Menginstall..."
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    if [[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
        rm composer-setup.php
        err "Composer installer corrupt"
        exit 1
    fi
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer >/dev/null
    rm composer-setup.php
    ok "Composer terinstall"
else
    ok "Composer sudah terinstall"
fi

# --- Node.js & NPM ---
NODE_REQUIRED="22"
info "Memeriksa Node.js (v${NODE_REQUIRED}+)..."

USE_NVM=false
if [[ -f "${HOME}/.nvm/nvm.sh" ]]; then
    source "${HOME}/.nvm/nvm.sh" --no-use 2>/dev/null || true
    USE_NVM=true
    ok "NVM terdeteksi"
fi

node_version_ok() {
    [[ -z "$1" ]] && return 1
    local ver="$1"
    # nvm current returns "system" for system node
    [[ "$ver" == "system" ]] && return 1
    local maj
    maj=$(echo "$ver" | cut -d. -f1)
    # Make sure it's numeric before comparing
    [[ "$maj" =~ ^[0-9]+$ ]] || return 1
    [[ "$maj" -ge "$NODE_REQUIRED" ]]
}

if $USE_NVM; then
    CURRENT_NODE=$(nvm current 2>/dev/null || true)
    [[ -z "$CURRENT_NODE" ]] && CURRENT_NODE="none"
    CURRENT_NODE="${CURRENT_NODE#v}"

    if [[ "$CURRENT_NODE" == "none" ]] || ! node_version_ok "$CURRENT_NODE"; then
        warn "Node.js v${NODE_REQUIRED}+ belum terinstall via NVM. Menginstall..."
        nvm install "$NODE_REQUIRED" >/dev/null 2>&1
        nvm use "$NODE_REQUIRED" >/dev/null 2>&1
    else
        nvm use "$CURRENT_NODE" >/dev/null 2>&1
    fi
    ok "Node.js $(node -v) + NPM $(npm -v) via NVM"
elif ! command -v node &>/dev/null || ! node_version_ok "$(node -v | cut -c2-)"; then
    warn "Node.js v${NODE_REQUIRED}+ belum terinstall. Menginstall dari repo..."
    case "$PKG" in
        apt)
            install_pkgs ca-certificates curl gnupg
            mkdir -p /etc/apt/keyrings
            curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
            echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_REQUIRED}.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
            apt-get update -qq
            install_pkgs nodejs
            ;;
        dnf)
            dnf module enable -y "nodejs:${NODE_REQUIRED}" >/dev/null 2>&1 || true
            install_pkgs nodejs
            ;;
    esac
    ok "Node.js $(node -v) + NPM $(npm -v) terinstall"
else
    ok "Node.js $(node -v) + NPM $(npm -v) sudah terinstall"
fi

# --- Git ---
info "Memeriksa Git..."
command -v git &>/dev/null || { warn "Git belum terinstall. Menginstall..."; install_pkgs git; ok "Git terinstall"; }

# ─────────────────────────────────────────────────────
# 4. Pilih web server
# ─────────────────────────────────────────────────────
echo ""
echo -e "Pilih web server:"
echo -e "  ${CYAN}1${NC}) Nginx + PHP-FPM  ${GREEN}(default)${NC}"
echo -e "  ${CYAN}2${NC}) Apache + PHP-FPM"
read -rp "Pilihan [1/2]: " WS_CHOICE
WS_CHOICE="${WS_CHOICE:-1}"

case "$WS_CHOICE" in
    2)
        WS="apache"
        WS_PKG="apache2"
        WS_SERVICE="apache2"
        WS_SITES_AVAILABLE="/etc/apache2/sites-available"
        WS_SITES_ENABLED="/etc/apache2/sites-enabled"
        WS_VHOST_FILE="${WS_SITES_AVAILABLE}/${APP_NAME}.conf"
        WS_VHOST_ENABLED="${WS_SITES_ENABLED}/${APP_NAME}.conf"
        WS_DEFAULT_DISABLED="${WS_SITES_ENABLED}/000-default.conf"
        APACHE_MODS_DIR="/etc/apache2/mods-enabled"
        WS_EXTRA="Apache"
        WS_BIN="apache2"
        ;;
    *)
        WS="nginx"
        WS_PKG="nginx"
        WS_SERVICE="nginx"
        WS_SITES_AVAILABLE="/etc/nginx/sites-available"
        WS_SITES_ENABLED="/etc/nginx/sites-enabled"
        WS_VHOST_FILE="${WS_SITES_AVAILABLE}/${APP_NAME}"
        APACHE_MODS_DIR=""
        WS_EXTRA="Nginx"
        WS_BIN="nginx"
        ;;
esac

if command -v "$WS_BIN" &>/dev/null; then
    ok "${WS_EXTRA} sudah terinstall"
else
    info "Menginstall ${WS_EXTRA}..."
    install_pkgs "$WS_PKG"
    ok "${WS_EXTRA} terinstall"
fi

# ─────────────────────────────────────────────────────
# 5. Setup Database
# ─────────────────────────────────────────────────────
info "Menyiapkan database SQLite..."
DB_FILE="${APP_DIR}/database/database.sqlite"
if [[ -f "$DB_FILE" ]]; then
    warn "database.sqlite sudah ada, melewati..."
else
    cp "${APP_DIR}/database/sample.sqlite" "$DB_FILE"
    ok "database.sqlite dibuat"
fi
chown "${APP_USER}:${APP_USER}" "$DB_FILE"

# ─────────────────────────────────────────────────────
# 6. Setup Aplikasi
# ─────────────────────────────────────────────────────
info "Menyiapkan aplikasi..."

cd "$APP_DIR"

# .env
if [[ ! -f .env ]]; then
    cp .env.example .env
    sed -i "s|APP_URL=.*|APP_URL=http://localhost:${APP_PORT}|" .env
    sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=sqlite/" .env
    # Hapus DB_HOST/DB_PORT/DB_DATABASE untuk sqlite biar clean
    sed -i "/^DB_HOST=/d" .env
    sed -i "/^DB_PORT=/d" .env
    sed -i "/^DB_DATABASE=/d" .env
    ok ".env dikonfigurasi"
fi

# Storage & cache permissions
mkdir -p storage/logs storage/framework/{cache,sessions,testing,views} bootstrap/cache
chown -R "${APP_USER}:${APP_USER}" storage bootstrap/cache "$DB_FILE"

# Composer
info "Menjalankan composer install..."
composer install --no-interaction --prefer-dist --no-dev -q
ok "Composer selesai"
# Pastikan vendor/ terbaca web server
chmod -R o+r vendor/ 2>/dev/null || true

# Generate key
php artisan key:generate --force --quiet
ok "APP_KEY generated"

# NPM build
info "Menjalankan npm install & build..."
npm install --silent 2>/dev/null
npm run build --silent 2>/dev/null
chown -R "${APP_USER}:${APP_USER}" public/build 2>/dev/null || true
ok "Frontend siap"

# Pastikan semua writable directory milik web server
chown -R "${APP_USER}:${APP_USER}" storage database bootstrap/cache 2>/dev/null || true
ok "Storage & database permissions fixed"

# Migrate
info "Menjalankan migrasi database..."
php artisan migrate --force --quiet
ok "Migrasi selesai"

php artisan db:seed --force --quiet 2>/dev/null || true
ok "Seeder selesai (default user: admin@autodns.local / admin)"

# ─────────────────────────────────────────────────────
# 7. Setup Web Server Vhost
# ─────────────────────────────────────────────────────
PHP_SOCKET=$(find /run/php /var/run -name "php*-fpm.sock" 2>/dev/null | head -1)
[[ -z "$PHP_SOCKET" ]] && PHP_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"

info "Membuat konfigurasi ${WS_EXTRA}..."

case "$WS" in
    nginx)
        cat > "$WS_VHOST_FILE" <<EOF
server {
    listen ${APP_PORT};
    server_name _;

    root ${APP_DIR}/public;
    index index.php;

    access_log /var/log/nginx/${APP_NAME}_access.log;
    error_log  /var/log/nginx/${APP_NAME}_error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
        ln -sf "$WS_VHOST_FILE" "${WS_SITES_ENABLED}/"
        # Remove default
        rm -f "${WS_SITES_ENABLED}/default"
        ;;
    apache)
        cat > "$WS_VHOST_FILE" <<EOF
<VirtualHost *:${APP_PORT}>
    ServerAdmin webmaster@localhost
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \\.php\$>
        SetHandler "proxy:unix:${PHP_SOCKET}|fcgi://localhost"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/${APP_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${APP_NAME}_access.log combined
</VirtualHost>
EOF
        # Enable rewrite + proxy modules
        a2enmod rewrite proxy_fcgi >/dev/null 2>&1 || true
        # Disable default, enable our site
        a2dissite 000-default >/dev/null 2>&1 || true
        a2ensite "${APP_NAME}.conf" >/dev/null 2>&1 || true
        # Change listen port
        if ! grep -q "^Listen ${APP_PORT}" /etc/apache2/ports.conf 2>/dev/null; then
            echo "Listen ${APP_PORT}" >> /etc/apache2/ports.conf
        fi
        ;;
esac

ok "Konfigurasi ${WS_EXTRA} dibuat di ${WS_VHOST_FILE}"

# ─────────────────────────────────────────────────────
# 9. Enable & restart services
# ─────────────────────────────────────────────────────
PHP_FPM_SERVICE_NAME="${PHP_FPM_SERVICE}"

info "Me-restart service..."
systemctl enable "$PHP_FPM_SERVICE_NAME" >/dev/null 2>&1 || true
systemctl restart "$PHP_FPM_SERVICE_NAME" >/dev/null 2>&1
systemctl enable "$WS_SERVICE" >/dev/null 2>&1 || true
systemctl restart "$WS_SERVICE" >/dev/null 2>&1

# Cek status
echo ""
echo -e "  ${CYAN}Service Status:${NC}"
for svc in "$PHP_FPM_SERVICE_NAME" "$WS_SERVICE"; do
    if systemctl is-active --quiet "$svc"; then
        echo -e "  ${GREEN}●${NC} $svc ${GREEN}(running)${NC}"
    else
        echo -e "  ${RED}✗${NC} $svc ${RED}(not running)${NC}"
    fi
done
ok "Service aktif: ${WS_SERVICE} + ${PHP_FPM_SERVICE_NAME}"

# ─────────────────────────────────────────────────────
# 10. Firewall info
# ─────────────────────────────────────────────────────
info "Memeriksa firewall..."
FIREWALL_CMD=""
if command -v ufw &>/dev/null && ufw status 2>/dev/null | grep -qi active; then
    FIREWALL_CMD="sudo ufw allow ${APP_PORT}/tcp"
elif command -v firewall-cmd &>/dev/null && firewall-cmd --state 2>/dev/null | grep -qi running; then
    FIREWALL_CMD="sudo firewall-cmd --add-port=${APP_PORT}/tcp --permanent && sudo firewall-cmd --reload"
fi

# ─────────────────────────────────────────────────────
# 11. IP detection & output
# ─────────────────────────────────────────────────────
LOCAL_IP=$(ip -4 route get 1 | awk '{print $7; exit}' 2>/dev/null || hostname -I | awk '{print $1}')
PUBLIC_IP=$(curl -4 -s --max-time 5 https://ipv4.icanhazip.com 2>/dev/null || echo "Gagal deteksi")

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN} ✅  AutoDNS Dashboard berhasil diinstall!${NC}"
echo ""
echo -e "     ${CYAN}Local:${NC}   http://${LOCAL_IP}:${APP_PORT}"
if [[ "$PUBLIC_IP" != "Gagal deteksi" ]]; then
    echo -e "     ${CYAN}Public:${NC}  http://${PUBLIC_IP}:${APP_PORT}"
fi
echo ""
echo -e "     ${YELLOW}Direktori:${NC} ${APP_DIR}"
echo -e "     ${YELLOW}Web Server:${NC} ${WS_EXTRA}"
echo -e "     ${YELLOW}Manage:${NC}    ${CYAN}systemctl restart${NC} ${WS_SERVICE}"
echo ""
if [[ -n "$FIREWALL_CMD" ]]; then
    echo -e "     ${YELLOW}⚠️  Firewall terdeteksi aktif. Izinkan port:${NC}"
    echo -e "     ${FIREWALL_CMD}"
    echo ""
fi
echo -e "     ${YELLOW}⚠️  Jangan lupa register akun pertama di browser!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
