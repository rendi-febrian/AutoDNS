<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/Auto_DNS_Domain-0f172a?style=for-the-badge&logo=cloudflare&logoColor=f38020">
    <img alt="Auto DNS Domain" src="https://img.shields.io/badge/Auto_DNS_Domain-0f172a?style=for-the-badge&logo=cloudflare&logoColor=f38020">
  </picture>
</p>

<p align="center">
  <strong>Auto-sync Cloudflare DNS A records to your server IP — no manual updates needed.</strong>
</p>

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel_13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP_8.3-777BB4?style=flat-square&logo=php&logoColor=white)
![Cloudflare](https://img.shields.io/badge/Cloudflare_API-F38020?style=flat-square&logo=cloudflare&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_Dark-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)

---

**Auto DNS Domain** is a self-hosted Laravel web app that connects to Cloudflare via API Token, tracks your A records, and automatically syncs them whenever your server IP changes.

---

</div>

## Features

| Feature | Description |
|---|---|
| **API Token Auth** | Enter Cloudflare API Token in the UI — no OAuth, no .env setup |
| **Live Zone Browser** | Browse all zones directly from Cloudflare API |
| **DNS Record Sync** | Pull records from any zone into local DB |
| **Domain Tracking** | Select A records to include in auto-sync |
| **Auto-Sync** | Update all tracked A records to current server IP — CLI or cron |
| **Activity Logs** | Full history of every DNS update with status & IP changes |
| **Import from Config** | Import domains from Nginx/Apache config files |
| **One-Click Install** | `bash install.sh` — detects OS, installs deps, creates vhost |
| **SSL via Certbot** | `bash add-domain.sh` → certbot + SSL on port 443 |
| **Dark UI** | Modern dark interface with Tailwind CSS |

## Requirements

- PHP 8.3+
- Composer
- Node.js & NPM (frontend build)
- Cloudflare API Token (permissions: `Zone:Read`, `DNS:Edit`)
- SQLite (default) or MySQL/PostgreSQL

## Quick Install

```bash
sudo mkdir -p /opt/autodns
sudo chown $USER:$USER /opt/autodns
git clone https://github.com/rendi-febrian/AutoDNS.git /opt/autodns
cd /opt/autodns
sudo bash install.sh
```

The installer will:

| Step | What it does |
|---|---|
| 🔍 | Detect OS (Ubuntu/Debian/Fedora/CentOS) |
| 📦 | Install PHP 8.3 + FPM + extensions, Composer, Node.js, Git |
| 🎨 | Choose Nginx or Apache |
| 🗄️ | Setup SQLite database |
| ⚙️ | Run `composer install`, `npm run build`, `migrate`, `seed` |
| 🌐 | Create web server vhost on port 80 |

After install:

```bash
# Seed default admin user (optional)
sudo -u www-data php artisan db:seed

# Add a domain
bash add-domain.sh example.com

# Visit
open http://<server-ip>
```

> Default user: `admin@autodns.local` / `admin`

### Uninstall

```bash
sudo bash uninstall.sh
```

Removes the repo directory and vhost config. PHP, Composer, Node, Git, and other vhosts remain untouched.

## Add a Domain

```bash
bash add-domain.sh example.com
```

The script:

1. Checks A record resolves to server IP (Cloudflare proxy IP allowed)
2. Updates `server_name` in vhost
3. Installs certbot + obtains SSL certificate
4. Injects SSL into vhost (port 443)
5. Sets up auto-renew cron
6. Finds zone in Cloudflare, creates/links A record
7. Adds to tracked domains

## Auto-Sync (Cron)

```bash
# Manual
php artisan dns:auto-sync

# Force update
php artisan dns:auto-sync --force
```

### Cron (every 5 min)

```cron
*/5 * * * * cd /opt/autodns && php artisan dns:auto-sync >> storage/logs/dns-sync.log 2>&1
```

## Usage

| Page | Route | Function |
|---|---|---|
| Dashboard | `/dashboard` | Overview stats, zones, recent activity |
| Cloudflare | `/cloudflare/accounts` | Manage API tokens, browse zones |
| DNS Records | `/zones/{zone}/records` | View, create, sync, track DNS records |
| Tracked Domains | `/tracked-domains` | Manage auto-sync list |
| DNS Update | `/dns/update` | Manual single or bulk DNS update |
| Activity Logs | `/logs` | Full history of DNS updates |

## Project Structure

```
├── app/
│   ├── Console/Commands/
│   │   ├── AutoSyncDns.php              # dns:auto-sync
│   │   └── DomainTrack.php              # domain:track
│   ├── Http/Controllers/
│   │   ├── CloudflareAccountController.php
│   │   ├── DnsController.php
│   │   └── TrackedDomainController.php
│   ├── Models/
│   │   ├── CloudflareAccount.php
│   │   ├── Zone.php
│   │   ├── DnsRecord.php
│   │   ├── TrackedDomain.php
│   │   └── DnsUpdateLog.php
│   └── Services/
│       ├── CloudflareService.php        # CF API v4 wrapper
│       └── ConfigParserService.php      # Nginx/Apache config parser
├── resources/views/                     # Blade + Tailwind dark UI
├── tests/                               # 49 PHPUnit tests
├── database/
│   ├── sample.sqlite                    # Empty SQLite placeholder
│   └── migrations/
├── install.sh                           # One-click installer
├── add-domain.sh                        # Add domain + SSL
├── uninstall.sh
├── auto-dns-sync.sh                     # Cron wrapper
└── README.md
```

## Security

- API tokens encrypted at rest (Laravel `encrypted` cast)
- No API keys in .env — all credentials via UI
- All routes behind authentication
- Input validation on all endpoints

## License

MIT — see [LICENSE](./LICENSE).
