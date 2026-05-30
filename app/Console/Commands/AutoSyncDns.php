<?php

namespace App\Console\Commands;

use App\Models\DnsRecord;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use App\Models\Zone;
use App\Models\CloudflareAccount;
use App\Services\CloudflareService;
use Illuminate\Console\Command;

class AutoSyncDns extends Command
{
    protected $signature = 'dns:auto-sync {--force : Force update even if IP unchanged}';
    protected $description = 'Auto-update all tracked A records to current server public IP';

    public function handle(): int
    {
        $service = new CloudflareService;
        $ip = $service->getPublicIp();

        if (!$ip) {
            $this->error('Failed to detect server public IP.');
            return self::FAILURE;
        }

        $this->info("Current public IP: {$ip}");

        // Auto-link unlinked domains
        $unlinked = TrackedDomain::whereNull('dns_record_id')->where('is_active', true)->get();
        foreach ($unlinked as $domain) {
            $zone = $this->resolveZone($domain->zone_name);
            if (!$zone) continue;

            $record = DnsRecord::where('zone_id', $zone->id)
                ->where('name', $domain->domain_name)
                ->where('type', 'A')
                ->first();

            if (!$record && $zone->cloudflareAccount) {
                $cf = new CloudflareService($zone->cloudflareAccount);
                $result = $cf->getDnsRecords($zone->zone_id);
                foreach ($result['result'] ?? [] as $rec) {
                    $recName = rtrim($rec['name'] ?? '', '.');
                    $domName = rtrim($domain->domain_name, '.');
                    if ($rec['type'] === 'A' && strcasecmp($recName, $domName) === 0) {
                        $record = DnsRecord::firstOrCreate(
                            ['record_id' => $rec['id']],
                            [
                                'zone_id' => $zone->id,
                                'type' => 'A',
                                'name' => $recName,
                                'content' => $rec['content'],
                                'ttl' => $rec['ttl'],
                                'proxied' => $rec['proxied'] ?? false,
                            ]
                        );
                        break;
                    }
                }
            }

            if (!$record && $zone->cloudflareAccount) {
                $cf = new CloudflareService($zone->cloudflareAccount);
                $createResult = $cf->createDnsRecord($zone->zone_id, [
                    'type' => 'A',
                    'name' => $domain->domain_name,
                    'content' => $ip,
                    'ttl' => 120,
                    'proxied' => false,
                ]);

                if (isset($createResult['success']) && $createResult['success']) {
                    $r = $createResult['result'];
                    $record = DnsRecord::create([
                        'zone_id' => $zone->id,
                        'record_id' => $r['id'],
                        'type' => $r['type'],
                        'name' => rtrim($r['name'] ?? '', '.'),
                        'content' => $r['content'],
                        'ttl' => $r['ttl'],
                        'proxied' => $r['proxied'] ?? false,
                    ]);
                }
            }

            if ($record) {
                $domain->update([
                    'dns_record_id' => $record->id,
                    'ip_address' => $record->content,
                    'last_synced_at' => now(),
                ]);
                DnsUpdateLog::create([
                    'tracked_domain_id' => $domain->id,
                    'zone_name' => $zone->name,
                    'record_name' => $record->name,
                    'record_type' => 'A',
                    'old_ip' => null,
                    'new_ip' => $record->content,
                    'status' => 'linked',
                    'response_message' => 'Auto-linked via cron',
                ]);
                $this->line("  [LINK] {$domain->domain_name} → {$record->content}");
            }
        }

        $domains = TrackedDomain::with('dnsRecord.zone.cloudflareAccount')
            ->where('is_active', true)
            ->whereNotNull('dns_record_id')
            ->get();

        if ($domains->isEmpty()) {
            $this->warn('No active tracked domains with linked DNS records.');
            return self::SUCCESS;
        }

        $this->info("Found {$domains->count()} tracked domains.");

        $successCount = 0;
        $failCount = 0;
        $skippedCount = 0;

        foreach ($domains as $domain) {
            $record = $domain->dnsRecord;

            if (!$record) {
                $this->warn("  [SKIP] {$domain->domain_name}: No linked DNS record.");
                $skippedCount++;
                continue;
            }

            if ($record->content === $ip && !$this->option('force')) {
                $this->line("  [OK]   {$domain->domain_name}: already {$ip}");
                $domain->update(['last_synced_at' => now()]);
                $record->update(['synced_at' => now()]);
                DnsUpdateLog::create([
                    'tracked_domain_id' => $domain->id,
                    'zone_name' => $record->zone->name,
                    'record_name' => $record->name,
                    'record_type' => $record->type,
                    'old_ip' => $ip,
                    'new_ip' => $ip,
                    'status' => 'nochange',
                    'response_message' => 'IP sudah sesuai',
                ]);
                $skippedCount++;
                continue;
            }

            $zone = $record->zone;
            $account = $zone->cloudflareAccount;
            $oldIp = $record->content;

            $this->line("  [UPD]  {$domain->domain_name}: {$oldIp} → {$ip}");

            $cfService = new CloudflareService($account);
            $result = $cfService->updateDnsRecord($zone->zone_id, $record->record_id, [
                'type' => $record->type,
                'name' => $record->name,
                'content' => $ip,
                'ttl' => $record->ttl,
                'proxied' => $record->proxied,
            ]);

            DnsUpdateLog::create([
                'tracked_domain_id' => $domain->id,
                'zone_name' => $zone->name,
                'record_name' => $record->name,
                'record_type' => $record->type,
                'old_ip' => $oldIp,
                'new_ip' => $ip,
                'status' => $result['success'] ? 'success' : 'failed',
                'response_message' => $result['success'] ? 'Auto-sync' : ($result['errors'][0]['message'] ?? 'Unknown error'),
            ]);

            if ($result['success']) {
                $record->update(['content' => $ip, 'synced_at' => now()]);
                $domain->update(['ip_address' => $ip, 'last_synced_at' => now()]);
                $successCount++;
            } else {
                $this->error("    └─ Failed: " . ($result['errors'][0]['message'] ?? 'Unknown'));
                $failCount++;
            }
        }

        $this->newLine();
        $this->info("Done. {$successCount} updated, {$failCount} failed, {$skippedCount} skipped.");

        return self::SUCCESS;
    }

    private function resolveZone(string $zoneName): ?Zone
    {
        $zone = Zone::where('name', $zoneName)->first();
        if ($zone) return $zone;

        foreach (CloudflareAccount::all() as $account) {
            $cf = new CloudflareService($account);
            $result = $cf->getZones();
            if (!isset($result['success']) || !$result['success']) continue;

            foreach ($result['result'] as $z) {
                if ($z['name'] === $zoneName) {
                    return Zone::create([
                        'cloudflare_account_id' => $account->id,
                        'zone_id' => $z['id'],
                        'name' => $z['name'],
                        'status' => $z['status'] ?? 'active',
                    ]);
                }
            }
        }

        return null;
    }
}
