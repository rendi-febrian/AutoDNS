#!/bin/bash
set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()  { echo -e "${CYAN}[INFO]${NC} $1"; }
ok()    { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()   { echo -e "${RED}[ERR]${NC}  $1"; }

APP_DIR="$(cd "$(dirname "$0")" && pwd)"

[[ $EUID -eq 0 ]] || { err "Jalankan dengan sudo: sudo bash uninstall.sh"; exit 1; }

echo ""
echo -e "${YELLOW}⚠️  AutoDNS akan di-uninstall. Yang akan dihapus:${NC}"
echo -e "   - ${APP_DIR}  (repo)"
echo -e "   - Vhost Nginx/Apache untuk autodns"
echo -e "   PHP, Composer, Node.js, Git ${GREEN}tidak${NC} disentuh."
echo -e "   Web server ${GREEN}tidak${NC} di-restart."
echo ""
read -rp "Lanjutkan? [y/N]: " CONFIRM
[[ "$CONFIRM" =~ ^[Yy]$ ]] || { info "Dibatalkan."; exit 0; }

# ── Hapus vhost ──
rm -f /etc/nginx/sites-available/autodns
rm -f /etc/nginx/sites-enabled/autodns
rm -f /etc/apache2/sites-available/autodns.conf
rm -f /etc/apache2/sites-enabled/autodns.conf
ok "Vhost autodns dihapus"

# ── Hapus repo ──
cd /
rm -rf "$APP_DIR"
ok "Direktori ${APP_DIR} dihapus"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN} ✅  AutoDNS berhasil di-uninstall.${NC}"
echo ""
echo -e "     PHP, Composer, Node.js, Git tetap terinstall."
echo -e "     Web server dan vhost lain tidak terganggu."
echo -e "     Database SQLite ikut terhapus (di dalam repo)."
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
