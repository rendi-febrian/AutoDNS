# AutoDNS Dashboard — Cara Pakai

## 1. Login / Register

- Buka URL aplikasi
- Register akun baru atau Login kalau sudah punya
- Setelah login, masuk ke halaman **Dashboard**

## 2. Hubungkan Cloudflare

1. Klik **Cloudflare** di sidebar atau buka `/cloudflare/accounts`
2. Masukkan **API Token** dari Cloudflare Dashboard → Profile → API Tokens
3. Klik **Verifikasi**
   - Token diverifikasi otomatis ke Cloudflare API
   - Nama akun & email dideteksi otomatis
4. Kalau berhasil, muncul kartu akun terhubung dengan info:
   - Nama akun · Email · Token (masked)

> **Buat token di**: https://dash.cloudflare.com/profile/api-tokens
> Pilih template *Edit zone DNS* atau kasih permission: `Zone:Read`, `DNS:Edit`, `Account:Read`

## 3. Browse Zones & Sync DNS Records

1. Dari kartu akun, klik **Browse Zones**
   - Menampilkan semua zone/domain di akun Cloudflare (data live dari API)
2. Klik **Lihat Records** pada zone yang ingin dikelola
   - Semua DNS record dari zone itu tersimpan ke database (`zones` & `dns_records`)
   - Muncul halaman DNS Records

## 4. Track Domain untuk Auto-Sync

> **Konsep**: Hanya DNS record A yang di-*track* yang akan ikut auto-sync.

### Cara 1 — Dari DNS Records
1. Buka zone → DNS Records
2. Cari record type **A**
3. Klik tombol **+ Track** pada record yang ingin di-track
4. Otomatis masuk ke Tracked Domains

### Cara 2 — Manual dari Tracked Domains
1. Buka **Tracked Domains**
2. Isi form *Add Domain*:
   - **Domain**: nama domain (contoh: `example.com`)
   - **Zone**: pilih zone dari daftar
   - **Link DNS Record**: pilih record A yang sesuai (opsional)
3. Klik **Add to Tracking**

### Cara 3 — Import dari Nginx/Apache Config
1. Buka **Tracked Domains**
2. Di form *Import from Config*:
   - Pilih **Nginx** — otomatis baca `/etc/nginx/sites-enabled/`
   - Pilih **Apache** — otomatis baca `/etc/apache2/sites-enabled/`
   - Pilih **Manual Paste** — ketik/paste domain (1 per line)
3. Klik **Import Domains**
4. Domain yang belum ada di tracking akan ditambahkan
   - Zone name dideteksi otomatis
   - DNS record A dicari & link otomatis kalau cocok

## 5. Auto-Sync (Update ke IP Server)

### Manual — Klik "Sync All to Server IP"
1. Buka **Tracked Domains**
2. Klik tombol **Sync All to Server IP**
   - Semua active tracked domain dengan link DNS record akan diupdate
   - IP server dideteksi otomatis dari `https://ipv4.icanhazip.com`
   - Hanya record yang IP-nya berbeda yang akan diupdate

### Otomatis — Cron Job
1. Edit file `auto-dns-sync.sh`:
   ```bash
   ARTISAN_PATH="/path/ke/project/artisan"   # ganti ke path real
   ```
2. Set cron job (contoh tiap 5 menit):
   ```cron
   */5 * * * * /path/ke/auto-dns-sync.sh
   ```
3. Atau langsung via artisan:
   ```cron
   */5 * * * * cd /path/ke/project && php artisan dns:auto-sync >> storage/logs/dns-sync.log 2>&1
   ```

### Via Artisan Command
```bash
php artisan dns:auto-sync                # update jika IP berubah
php artisan dns:auto-sync --force        # paksa update meski IP sama
```

## 6. Manual Update DNS

### Single Record
1. Buka **DNS Update** → **Single Update**
2. Pilih DNS record
3. Masukkan IP baru
4. Klik **Update Single**

### Bulk Update (Satu Zone)
1. Buka **DNS Update** → **Bulk Update**
2. Pilih **Zone**
3. Centang record yang ingin diupdate (ada Select All)
4. Masukkan IP baru
5. Klik **Bulk Update**

## 7. Buat DNS Record Baru

1. Buka zone → DNS Records
2. Di form **New DNS Record**:
   - **Type**: A, AAAA, CNAME, MX, TXT, NS, SRV
   - **Name**: subdomain atau @ untuk root
   - **Content**: IP atau target
   - **TTL**: Auto (default) atau custom
   - **Proxy**: centang untuk Cloudflare Proxy (orange cloud)
3. Klik **Create**
   - Record dibuat langsung di Cloudflare API + tersimpan di database

## 8. Activity Logs

1. Buka **Logs** di sidebar
2. Melihat riwayat update: record, zone, old IP → new IP, status, timestamp
3. Log tercatat dari:
   - Sync All (manual)
   - Single Update
   - Bulk Update
   - Artisan `dns:auto-sync` (cron)

## 9. Dashboard

Halaman utama menampilkan:
- **Server IP** — IP publik server saat ini
- **Total Zones** — zone yang sudah di-sync
- **Tracked Domains** — active / total
- **Cloudflare Accounts** — jumlah akun terhubung
- **Zone list** — shortcut ke DNS records tiap zone
- **Recent Activity** — 10 log terbaru

## Catatan Penting

- **API Token dienkripsi** (Laravel encrypted cast) — aman di database
- **Auto-sync hanya untuk A record** yang sudah di-track & punya link DNS record
- **Zone & record bisa di-sync ulang** kapan saja via tombol Sync
- **Putuskan koneksi** Cloudflare: dari kartu akun, klik **Putuskan**
