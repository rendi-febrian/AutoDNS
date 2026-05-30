<?php

namespace App\Console\Commands;

use App\Models\DnsRecord;
use App\Models\TrackedDomain;
use App\Models\Zone;
use App\Services\CloudflareService;
use App\Services\ConfigParserService;
use Illuminate\Console\Command;

class DomainTrack extends Command
{
    protected $signature = 'domain:track
        {domain : Domain name to track (e.g. example.com)}
        {--zone= : Zone name (auto-detected if omitted)}
        {--ip= : Server IP (auto-detected if omitted)}
        {--force : Skip DNS resolution check}';

    protected $description = 'Add a domain to tracked domains and link/create its A record in Cloudflare';

    public function handle(ConfigParserService $parser): int
    {
        $domain = $this->argument('domain');
        $zoneName = $this->option('zone') ?? $parser->detectZone($domain);

        // Already tracked?
        if (TrackedDomain::where('domain_name', $domain)->exists()) {
            $this->warn("Domain {$domain} is already tracked.");
            return self::SUCCESS;
        }

        // Detect server IP
        $ip = $this->option('ip');
        if (!$ip) {
            $cf = new CloudflareService;
            $ip = $cf->getPublicIp();
        }
        if (!$ip) {
            $this->error('Could not detect server IP. Provide --ip or check internet.');
            return self::FAILURE;
        }

        // DNS check (skip with --force)
        if (!$this->option('force')) {
            $resolved = gethostbyname($domain);
            if ($resolved === $domain) {
                $this->error("Domain {$domain} does not resolve to any IP.");
                return self::FAILURE;
            }
            if ($resolved !== $ip) {
                $this->error("Domain {$domain} resolves to {$resolved}, not server IP {$ip}.");
                $this->line('Use --force to add anyway.');
                return self::FAILURE;
            }
            $this->line("  [OK]   {$domain} → {$resolved} (matches server IP)");
        }

        // Find local DNS record
        $record = DnsRecord::where('name', $domain)->where('type', 'A')->first();

        // If no local record, try to find/create via Cloudflare
        if (!$record) {
            $zone = Zone::where('name', $zoneName)->first();
            if ($zone && $zone->cloudflareAccount) {
                $this->line("  [CF]   Checking Cloudflare zone {$zoneName}...");
                $cf = new CloudflareService($zone->cloudflareAccount);
                $records = $cf->getDnsRecords($zone->zone_id);

                if (isset($records['success']) && $records['success']) {
                    foreach ($records['result'] as $r) {
                        if ($r['type'] === 'A' && ($r['name'] === $domain || $r['name'] === $domain . '.')) {
                            $record = DnsRecord::updateOrCreate(
                                ['record_id' => $r['id']],
                                [
                                    'zone_id' => $zone->id,
                                    'type' => $r['type'],
                                    'name' => $r['name'],
                                    'content' => $r['content'],
                                    'ttl' => $r['ttl'],
                                    'proxied' => $r['proxied'],
                                ]
                            );
                            $this->line("  [OK]   Found existing A record in Cloudflare.");
                            break;
                        }
                    }
                }

                // Create A record in Cloudflare if missing
                if (!$record) {
                    $this->line("  [CF]   Creating A record {$domain} → {$ip}...");
                    $result = $cf->createDnsRecord($zone->zone_id, [
                        'type' => 'A',
                        'name' => $domain,
                        'content' => $ip,
                        'ttl' => 120,
                        'proxied' => false,
                    ]);

                    if (isset($result['success']) && $result['success']) {
                        $r = $result['result'];
                        $record = DnsRecord::create([
                            'zone_id' => $zone->id,
                            'record_id' => $r['id'],
                            'type' => $r['type'],
                            'name' => $r['name'],
                            'content' => $r['content'],
                            'ttl' => $r['ttl'],
                            'proxied' => $r['proxied'],
                        ]);
                        $this->line("  [OK]   A record created in Cloudflare.");
                    } else {
                        $this->warn('  [WARN] Could not create A record: ' . ($result['errors'][0]['message'] ?? 'Unknown'));
                    }
                }
            } else {
                $this->warn("  [WARN] No Cloudflare zone '{$zoneName}' found locally.");
                $this->warn('  [WARN] Only linking domain without DNS record — auto-sync will be inactive.');
                $this->warn('  [WARN] Sync a zone via web UI first, or run this command after adding the zone.');
            }
        }

        // Add to tracked domains
        $tracked = TrackedDomain::create([
            'domain_name' => $domain,
            'zone_name' => $zoneName,
            'dns_record_id' => $record?->id,
            'ip_address' => $record?->content ?? $ip,
            'is_active' => true,
        ]);

        $this->info("Domain {$domain} (zone: {$zoneName}) added to tracking.");
        if ($record) {
            $this->line("Linked to DNS record: {$record->name} → {$record->content}");
            $this->line("Auto-sync is active for this domain.");
        } else {
            $this->warn('No DNS record linked — sync a zone in web UI, then link this domain.');
        }

        return self::SUCCESS;
    }
}
