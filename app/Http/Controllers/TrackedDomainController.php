<?php

namespace App\Http\Controllers;

use App\Models\DnsRecord;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use App\Models\Zone;
use App\Services\CloudflareService;
use App\Services\ConfigParserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackedDomainController extends Controller
{
    public function index(): View
    {
        $trackedDomains = TrackedDomain::with('dnsRecord.zone')->latest()->get();
        $zones = Zone::with('cloudflareAccount')->get();
        return view('tracked-domains.index', compact('trackedDomains', 'zones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain_name' => 'required|string|max:255|unique:tracked_domains,domain_name',
            'zone_name' => 'required|string|max:255',
            'dns_record_id' => 'nullable|exists:dns_records,id',
        ]);

        $tracked = TrackedDomain::create($validated);

        if ($tracked->dns_record_id && $tracked->dnsRecord) {
            $tracked->update(['ip_address' => $tracked->dnsRecord->content]);
        }

        return to_route('tracked-domains.index')->with('success', "Domain {$validated['domain_name']} added to tracking.");
    }

    public function update(Request $request, TrackedDomain $trackedDomain): RedirectResponse
    {
        $validated = $request->validate([
            'domain_name' => 'required|string|max:255|unique:tracked_domains,domain_name,' . $trackedDomain->id,
            'zone_name' => 'required|string|max:255',
            'dns_record_id' => 'nullable|exists:dns_records,id',
            'is_active' => 'boolean',
        ]);

        $trackedDomain->update($validated);

        if ($trackedDomain->dns_record_id && $trackedDomain->dnsRecord) {
            $trackedDomain->update(['ip_address' => $trackedDomain->dnsRecord->content]);
        }

        return to_route('tracked-domains.index')->with('success', "Domain {$validated['domain_name']} updated.");
    }

    public function destroy(TrackedDomain $trackedDomain): RedirectResponse
    {
        $trackedDomain->delete();
        return to_route('tracked-domains.index')->with('success', 'Domain removed from tracking.');
    }

    public function syncAll(): RedirectResponse
    {
        $service = new CloudflareService;
        $ip = $service->getPublicIp();

        if (!$ip) {
            return back()->with('error', 'Failed to detect server public IP.');
        }

        // Auto-link domains without dns_record_id
        $unlinked = TrackedDomain::whereNull('dns_record_id')->where('is_active', true)->get();
        foreach ($unlinked as $domain) {
            $zone = Zone::where('name', $domain->zone_name)->first();
            if (!$zone) continue;

            $record = DnsRecord::where('zone_id', $zone->id)
                ->where('name', $domain->domain_name)
                ->where('type', 'A')
                ->first();

            if ($record) {
                $domain->update([
                    'dns_record_id' => $record->id,
                    'ip_address' => $record->content,
                ]);
            }
        }

        $domains = TrackedDomain::with('dnsRecord.zone.cloudflareAccount')
            ->where('is_active', true)
            ->whereNotNull('dns_record_id')
            ->get();

        if ($domains->isEmpty()) {
            $total = TrackedDomain::count();
            $msg = $total > 0
                ? "No domains with linked DNS records. Sync zone records first, then run Resolve DNS to link them."
                : 'No tracked domains found. Add domains first.';
            return back()->with('error', $msg);
        }

        $successCount = 0;
        $failCount = 0;
        $ipChanged = false;

        foreach ($domains as $domain) {
            $record = $domain->dnsRecord;
            $zone = $record->zone;
            $account = $zone->cloudflareAccount;

            if ($record->content === $ip) {
                continue;
            }

            $ipChanged = true;
            $oldIp = $record->content;
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
                'response_message' => $result['success'] ? 'Updated via sync-all' : ($result['errors'][0]['message'] ?? 'Unknown error'),
            ]);

            if ($result['success']) {
                $record->update(['content' => $ip, 'synced_at' => now()]);
                $domain->update(['ip_address' => $ip, 'last_synced_at' => now()]);
                $successCount++;
            } else {
                $failCount++;
            }
        }

        if (!$ipChanged) {
            return back()->with('info', 'All domains already match current IP (' . $ip . '). No update needed.');
        }

        $message = "Synced {$successCount} domains to IP {$ip}.";
        if ($failCount > 0) $message .= " {$failCount} failed.";

        return back()->with('success', $message);
    }

    public function importConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'config_type' => 'required|in:nginx,apache,manual',
            'config_content' => 'nullable|string',
        ]);

        $parser = new ConfigParserService;
        $domains = [];

        if ($validated['config_type'] === 'nginx') {
            $domains = $parser->getNginxSitesEnabled();
            if (empty($domains) && !empty($validated['config_content'])) {
                $domains = $parser->parseNginxConfig($validated['config_content']);
            }
        } elseif ($validated['config_type'] === 'apache') {
            $domains = $parser->getApacheSitesEnabled();
            if (empty($domains) && !empty($validated['config_content'])) {
                $domains = $parser->parseApacheConfig($validated['config_content']);
            }
        } else {
            $domains = array_filter(array_map('trim', explode("\n", $validated['config_content'] ?? '')));
        }

        if (empty($domains)) {
            $hint = $validated['config_type'] === 'manual'
                ? 'Paste domain list atau konfigurasi di textarea.'
                : 'PHP tidak bisa baca /etc/*/sites-enabled/ (open_basedir/permission). Coba paste konfigurasi langsung.';
            return back()->with('error', 'No domains found. ' . $hint);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($domains as $domain) {
            if (TrackedDomain::where('domain_name', $domain)->exists()) {
                $skipped++;
                continue;
            }

            $zoneName = $parser->detectZone($domain);
            $zone = Zone::where('name', $zoneName)->first();

            $record = null;
            if ($zone) {
                $record = DnsRecord::where('zone_id', $zone->id)
                    ->where('name', $domain)
                    ->where('type', 'A')
                    ->first();
            }

            TrackedDomain::create([
                'domain_name' => $domain,
                'zone_name' => $zoneName,
                'dns_record_id' => $record?->id,
                'ip_address' => $record?->content,
                'is_active' => true,
            ]);

            $imported++;
        }

        return back()->with('success', "Imported {$imported} domains" . ($skipped > 0 ? ", {$skipped} skipped (duplicates)" : ''));
    }

    public function resolveIps(): RedirectResponse
    {
        $domains = TrackedDomain::with('dnsRecord.zone.cloudflareAccount')->get();
        if ($domains->isEmpty()) {
            return back()->with('error', 'No tracked domains to resolve.');
        }

        $resolved = 0;
        $cfResolved = 0;
        $failed = 0;

        foreach ($domains as $domain) {
            $records = @dns_get_record($domain->domain_name, DNS_A);
            $ip = $records[0]['ip'] ?? null;

            if ($ip && CloudflareService::isCloudflareIp($ip)) {
                $realIp = null;

                if ($domain->dnsRecord) {
                    $account = $domain->dnsRecord->zone?->cloudflareAccount;
                    if ($account) {
                        $cf = new CloudflareService($account);
                        $result = $cf->getDnsRecord(
                            $domain->dnsRecord->zone->zone_id,
                            $domain->dnsRecord->record_id
                        );
                        $realIp = $result['result']['content'] ?? null;
                    }
                }

                if (!$realIp) {
                    // Try to find the record via CF API by zone + domain name
                    $zone = Zone::where('name', $domain->zone_name)->first();
                    if ($zone && $zone->cloudflareAccount) {
                        $cf = new CloudflareService($zone->cloudflareAccount);
                        $records = $cf->getDnsRecords($zone->zone_id);
                        foreach ($records['result'] ?? [] as $rec) {
                            if ($rec['type'] === 'A' && $rec['name'] === $domain->domain_name) {
                                $realIp = $rec['content'];
                                // Auto-link the record
                                $dnsRec = DnsRecord::firstOrCreate(
                                    ['record_id' => $rec['id']],
                                    [
                                        'zone_id' => $zone->id,
                                        'type' => 'A',
                                        'name' => $rec['name'],
                                        'content' => $rec['content'],
                                        'ttl' => $rec['ttl'],
                                        'proxied' => $rec['proxied'] ?? false,
                                    ]
                                );
                                $domain->update([
                                    'dns_record_id' => $dnsRec->id,
                                    'ip_address' => $realIp,
                                ]);
                                break;
                            }
                        }
                    }
                }

                if ($realIp) {
                    $domain->update(['ip_address' => $realIp]);
                    $resolved++;
                    $cfResolved++;
                    continue;
                }
            }

            if ($ip) {
                $domain->update(['ip_address' => $ip]);
                $resolved++;
            } else {
                $failed++;
            }
        }

        $msg = "Resolved {$resolved} domains via DNS.";
        if ($cfResolved > 0) $msg .= " {$cfResolved} dari Cloudflare API (origin IP).";
        if ($failed > 0) $msg .= " {$failed} failed.";

        return back()->with('success', $msg);
    }
}
