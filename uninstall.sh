#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_NAME="autodns"

[[ $EUID -eq 0 ]] || { err "Jalankan dengan sudo: sudo bash uninstall.sh"; exit 1; }

echo ""
echo -e "${YELLOW}⚠️  AutoDNS akan di-uninstall. File berikut akan dihapus:${NC}"
echo -e "   - ${APP_DIR}        (repo aplikasi)"
echo -e "   - /etc/systemd/system/${APP_NAME}.service"
echo ""
echo -e "   Apache/Nginx config akan ${RED}dinonaktifkan${NC}, tidak dihapus."
echo -e "   PHP, Composer, Node.js, Git ${GREEN}tidak${NC} akan dihapus."
echo ""
read -rp "Lanjutkan? [y/N]: " CONFIRM
[[ "$CONFIRM" =~ ^[Yy]$ ]] || { info "Dibatalkan."; exit 0; }

# ── Hapus systemd service ──
if systemctl is-enabled --quiet "${APP_NAME}.service" 2>/dev/null; then
    systemctl disable "${APP_NAME}.service" >/dev/null 2>&1
    info "${APP_NAME}.service dinonaktifkan"
fi
if [[ -f "/etc/systemd/system/${APP_NAME}.service" ]]; then
    rm -f "/etc/systemd/system/${APP_NAME}.service"
    systemctl daemon-reload
    ok "${APP_NAME}.service dihapus"
fi

# ── Nonaktifkan web server vhost ──
# Deteksi web server dari yang aktif
if command -v nginx &>/dev/null; then
    WS_SERVICE="nginx"
    WS_SITES_ENABLED="/etc/nginx/sites-enabled"
    WS_SITES_AVAILABLE="/etc/nginx/sites-available"
    WS_VHOST="${WS_SITES_AVAILABLE}/${APP_NAME}"
    rm -f "${WS_SITES_ENABLED}/${APP_NAME}" 2>/dev/null
    rm -f "$WS_VHOST" 2>/dev/null
    ok "Nginx vhost ${APP_NAME} dinonaktifkan"
fi

if command -v apache2 &>/dev/null; then
    WS_SERVICE="apache2"
    WS_SITES_ENABLED="/etc/apache2/sites-enabled"
    WS_SITES_AVAILABLE="/etc/apache2/sites-available"
    WS_VHOST="${WS_SITES_AVAILABLE}/${APP_NAME}.conf"
    a2dissite "${APP_NAME}.conf" >/dev/null 2>&1 || true
    rm -f "$WS_VHOST" 2>/dev/null
    ok "Apache vhost ${APP_NAME} dinonaktifkan"
fi

# ── Restart web server ──
if command -v nginx &>/dev/null; then
    systemctl reload nginx >/dev/null 2>&1 || systemctl restart nginx >/dev/null 2>&1 || true
fi
if command -v apache2 &>/dev/null; then
    systemctl reload apache2 >/dev/null 2>&1 || systemctl restart apache2 >/dev/null 2>&1 || true
fi

# ── Hapus repo ──
cd /
rm -rf "$APP_DIR"
ok "Direktori ${APP_DIR} dihapus"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN} ✅  AutoDNS berhasil di-uninstall.${NC}"
echo ""
echo -e "     PHP, Composer, Node.js, Git tetap terinstall."
echo -e "     Database SQLite ikut terhapus (di dalam repo)."
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
