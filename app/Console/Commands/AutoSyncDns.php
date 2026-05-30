<?php

namespace App\Console\Commands;

use App\Models\DnsRecord;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
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
}
