<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/AutoDNS_Dashboard-0f172a?style=for-the-badge&logo=cloudflare&logoColor=f38020">
    <img alt="AutoDNS Dashboard" src="https://img.shields.io/badge/AutoDNS_Dashboard-0f172a?style=for-the-badge&logo=cloudflare&logoColor=f38020">
  </picture>
</p>

<p align="center">
  <strong>Auto-update Cloudflare DNS A records to your server IP — automatically.</strong>
</p>

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel_13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP_8.3-777BB4?style=flat-square&logo=php&logoColor=white)
![Cloudflare](https://img.shields.io/badge/Cloudflare_API-F38020?style=flat-square&logo=cloudflare&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_Dark-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)

---

[Overview](#overview) •
[Features](#features) •
[Installation](#installation) •
[Usage](#usage) •
[Auto-Sync](#auto-sync-cron) •
[API Reference](#api-reference) •
[Project Structure](#project-structure) •
[Testing](#testing) •
[Security](#security) •
[Author](#author) •
[License](#license)

---

</div>

## Overview

**AutoDNS Dashboard** is a self-hosted Laravel web application that connects to your Cloudflare account via API Token and lets you selectively track A records for automatic DNS synchronization.

Whenever your server IP changes, run one command (or set a cron job) and all tracked A records update instantly to the new IP via the Cloudflare API.

### Who is this for?

- **Developers & SysAdmins** managing VPS servers with dynamic or changing IPs
- **Self-hosters** who need to keep their Cloudflare DNS records in sync
- **Agencies** managing multiple domains across multiple Cloudflare accounts

---

## Features

| Feature | Description |
|---|---|
| **🔐 API Token Auth** | Enter your Cloudflare API Token directly in the UI — no OAuth, no .env config |
| **🔍 Browse Zones** | View all zones from Cloudflare API in real-time |
| **📋 Sync DNS Records** | Pull all DNS records from a zone into the local database |
| **🎯 Track Domains** | Selectively choose which A records to include in auto-sync |
| **🔄 Auto-Sync** | Update all tracked A records to current server IP — manually or via cron |
| **📝 Manual Update** | Update single or bulk DNS records on demand |
| **📂 Import Config** | Import domains from Nginx/Apache config files |
| **📊 Activity Logs** | Full history of every DNS update with status and IP changes |
| **⚡ Artisan Command** | `php artisan dns:auto-sync` for cron job automation |
| **🌑 Dark UI** | Clean, modern dark interface built with Tailwind CSS |

---

## Tech Stack

```
Laravel 13 + Breeze (Blade) + Tailwind CSS + Cloudflare API v4 + SQLite/MySQL
```

---

## Requirements

- PHP 8.3+
- Composer
- Node.js & NPM (for frontend build)
- Cloudflare account with **API Token** (permissions: `Zone:Read`, `DNS:Edit`, `User:Read`)
- SQLite (default) or MySQL/PostgreSQL

---

## Installation

### Option A — One-Click Install (System Service)

Install with Nginx or Apache + PHP-FPM as a systemd service on port **26298**:

```bash
git clone https://github.com/rendi-febrian/autodns-dashboard.git /opt/autodns
cd /opt/autodns
sudo bash install.sh
```

The `install.sh` script will:

| Step | What it does |
|---|---|
| 🔍 | Detect OS (Ubuntu/Debian/Fedora/CentOS) |
| 📦 | Install PHP 8.3 + extensions + PHP-FPM, Composer, Node.js, Git |
| 🎨 | Let you choose **Nginx** or **Apache** |
| 🗄️ | Setup SQLite database from `database/sample.sqlite` |
| ⚙️ | Run `composer install`, `npm run build`, `php artisan migrate` |
| 🌐 | Create web server virtual host on port `26298` |
| 🔄 | Enable & restart all services |
| 📡 | Show local & public access URLs |

After install, open `http://<server-ip>:26298` and register your first account.

### Option B — Manual Setup

```bash
git clone https://github.com/rendi-febrian/autodns-dashboard.git
cd autodns-dashboard

# Setup SQLite database
cp database/sample.sqlite database/database.sqlite

# Setup environment & dependencies
composer run setup

# Edit .env — set APP_URL, etc.
# Run the application (development only)
php artisan serve
```

Then open `http://localhost:8000`.

> **Note**: For development use only. For production, use Option A or configure Nginx/Apache manually.

### Firewall

If you're using a firewall, allow port **26298**:

```bash
# UFW
sudo ufw allow 26298/tcp

# FirewallD
sudo firewall-cmd --add-port=26298/tcp --permanent
sudo firewall-cmd --reload
```

---

## Cloudflare API Token

Before using the app, you need to create an API Token in your Cloudflare dashboard.

### Step-by-step

1. Login ke [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Buka **My Profile** → **API Tokens** (atau langsung ke `https://dash.cloudflare.com/profile/api-tokens`)
3. Klik **Create Token**
4. Pilih template **Edit zone DNS**
5. Di bagian **Permissions**, pastikan setelan seperti ini:

   | Item | Value |
   |---|---|
   | Permissions | `Zone` → `DNS` → `Edit` |
   | Permissions | `Zone` → `Zone` → `Read` |
   | Permissions | `Account` → `Account Settings` → `Read` |
   | Zone Resources | `Include` → `All zones` (atau pilih spesifik) |
   | TTL | `No end date` (atau sesuai kebutuhan) |

   > **Optional**: Tambah permission `User` → `User Details` → `Read` untuk auto-detect nama & email akun.

6. Klik **Continue to Summary**, lalu **Create Token**
7. Copy token yang muncul (bentuknya `cf_...`), simpan di tempat aman
8. Masukkan token tersebut ke form di halaman **Cloudflare** → **Tambah Akun Cloudflare**

### Catatan Penting

- Token hanya ditampilkan **sekali** saat dibuat — simpan baik-baik
- Di aplikasi, token disimpan **terenkripsi** di database
- Kalau lupa, buat token baru dan ganti di aplikasi
- Gunakan token dengan **minimal permission** yang diperlukan (prinsip least privilege)

---

## Usage

### Quick Walkthrough

| Step | Action | Description |
|---|---|---|
| 1 | Register / Login | Create your first account |
| 2 | Connect Cloudflare | Enter API Token → auto-detect name & email |
| 3 | Browse Zones | View all zones from Cloudflare API |
| 4 | Sync DNS Records | Pull records into database |
| 5 | Track Domains | Click **+ Track** on A records you want to auto-sync |
| 6 | Sync All | Click **Sync All to Server IP** or set up cron |

### Detailed Guides

| Guide | Description |
|---|---|
| [📖 HOWTO.md](./HOWTO.md) | Complete walkthrough: connecting Cloudflare, tracking domains, auto-sync, import config, logs, and more |
| [📘 MVP.md](./MVP.md) | Project architecture: models, services, controllers, routes, and data flow |

### Key Pages

| Page | Route | Function |
|---|---|---|
| Dashboard | `/dashboard` | Overview stats, zones, recent activity |
| Cloudflare | `/cloudflare/accounts` | Manage API tokens, browse zones |
| DNS Records | `/zones/{zone}/records` | View, create, sync, track DNS records |
| Tracked Domains | `/tracked-domains` | Manage auto-sync list, sync all, import config |
| DNS Update | `/dns/update` | Manual single or bulk DNS update |
| Activity Logs | `/logs` | Full history of all DNS updates |

---

## Auto-Sync (Cron)

### Manual

```bash
php artisan dns:auto-sync

# Force update even if IP hasn't changed
php artisan dns:auto-sync --force
```

### Cron Job (every 5 minutes)

```cron
*/5 * * * * cd /opt/autodns && php artisan dns:auto-sync >> storage/logs/dns-sync.log 2>&1
```

### Using Shell Wrapper

```bash
# Edit auto-dns-sync.sh → set ARTISAN_PATH
# Then add to crontab:
*/5 * * * * /opt/autodns/auto-dns-sync.sh
```

---

## API Reference

This project uses the [Cloudflare API v4](https://api.cloudflare.com) internally. Key endpoints used:

| Cloudflare API | Purpose | Permission Required |
|---|---|---|
| `GET /user/tokens/verify` | Verify API token | — |
| `GET /user` | Get account email & username | `User:Read` |
| `GET /zones` | List all zones | `Zone:Read` |
| `GET /zones/{id}/dns_records` | List DNS records | `Zone:Read` |
| `PUT /zones/{id}/dns_records/{id}` | Update A record | `DNS:Edit` |
| `POST /zones/{id}/dns_records` | Create DNS record | `DNS:Edit` |

There is **no public REST API** — all operations are performed via the web UI or the `dns:auto-sync` Artisan command.

---

## Project Structure

```
├── app/
│   ├── Console/Commands/AutoSyncDns.php    # dns:auto-sync artisan command
│   ├── Http/Controllers/
│   │   ├── CloudflareAccountController.php  # API token management
│   │   ├── DnsController.php                # DNS records & updates
│   │   └── TrackedDomainController.php      # Tracked domains CRUD
│   ├── Models/
│   │   ├── CloudflareAccount.php            # Encrypted API token
│   │   ├── Zone.php                         # Cloudflare zone
│   │   ├── DnsRecord.php                    # DNS record (A, CNAME, etc.)
│   │   ├── TrackedDomain.php                # Domain selected for auto-sync
│   │   └── DnsUpdateLog.php                 # Update history
│   └── Services/
│       ├── CloudflareService.php            # CF API v4 wrapper
│       └── ConfigParserService.php          # Nginx/Apache config parser
├── resources/views/                         # Blade + Tailwind dark UI
├── tests/                                   # PHPUnit test suite (49 tests)
├── database/
│   ├── sample.sqlite                        # Empty SQLite placeholder
│   └── migrations/                          # 5 migration files
├── HOWTO.md                                 # Full usage guide
├── MVP.md                                   # Architecture documentation
├── install.sh                               # One-click install script
├── auto-dns-sync.sh                         # Cron wrapper script
└── README.md                                # This file
```

---

## Testing

Run the full test suite:

```bash
php artisan test
```

**49 tests** covering:

| Category | Tests | What's verified |
|---|---|---|
| 🔒 Guest Access | 1 | Unauthenticated users redirected to login |
| 🔐 Auth | 6 | Login, register, password reset, email verification |
| 📊 Dashboard | 4 | Stats rendering, zones & logs display |
| ☁️ Cloudflare | 4 | Account page, existing accounts, token validation |
| 📋 DNS Records | 1 | Zone records page loads with forms |
| 🎯 Tracked Domains | 7 | CRUD, validation, duplicates, import, sync |
| 📝 DNS Update | 1 | Update page loads with zone/record selectors |
| 📜 Logs | 2 | Activity logs page & data display |
| ⚙️ Services | 5 | ConfigParserService: nginx, apache, zone detection |
| 👤 Profile | 1 | Profile edit page |

---

## Security

- **API Tokens encrypted at rest** using Laravel's `encrypted` cast
- **No OAuth flow** — token stays in your database, never shared
- **No API keys in .env** — all credentials managed via the UI
- **Authentication required** — every route behind auth + email verification middleware
- **Input validation** — all inputs validated before hitting Cloudflare API
- **SQLite database ignored** by `.gitignore` — real data never committed

---

## Author

**Rendi Febrian**

[![GitHub](https://img.shields.io/badge/GitHub-181717?style=flat-square&logo=github&logoColor=white)](https://github.com/rendi-febrian)
[![Website](https://img.shields.io/badge/Website-0f172a?style=flat-square&logo=google-chrome&logoColor=white)](https://www.rendifebrian.com)
[![Twitter](https://img.shields.io/badge/@rendifebrian__-000?style=flat-square&logo=x&logoColor=white)](https://twitter.com/rendifebrian__)

- 💼 Founder at **Codenub**
- 📍 Lampung, Indonesia
- 🔧 Building tools for developers

---

## License

MIT — see [LICENSE](./LICENSE).

---

<p align="center">
  <sub>Built with ❤️ using Laravel & Cloudflare API</sub>
</p>
