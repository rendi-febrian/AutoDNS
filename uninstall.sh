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
echo -e "${YELLOW}⚠️  AutoDNS akan di-uninstall.${NC}"
echo -e "   Hanya ${RED}${APP_DIR}${NC} yang akan dihapus."
echo -e "   Web server, PHP, Composer, Node.js, Git ${GREEN}tidak${NC} disentuh."
echo ""
read -rp "Lanjutkan? [y/N]: " CONFIRM
[[ "$CONFIRM" =~ ^[Yy]$ ]] || { info "Dibatalkan."; exit 0; }

cd /
rm -rf "$APP_DIR"
ok "Direktori ${APP_DIR} dihapus"

echo ""
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN} ✅  AutoDNS berhasil di-uninstall.${NC}"
echo ""
echo -e "     Hanya repo yang dihapus."
echo -e "     Web server, PHP, Composer, Node.js, Git tetap utuh."
echo -e "     Konfigurasi vhost masih ada — hapus manual jika perlu."
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
