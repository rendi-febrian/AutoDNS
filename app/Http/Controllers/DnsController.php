<?php

namespace App\Http\Controllers;

use App\Models\CloudflareAccount;
use App\Models\DnsRecord;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use App\Models\Zone;
use App\Services\CloudflareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DnsController extends Controller
{
    public function records(Zone $zone): View
    {
        $zone->load('cloudflareAccount', 'dnsRecords');
        return view('dns.records', compact('zone'));
    }

    public function syncRecords(Zone $zone): RedirectResponse
    {
        $service = new CloudflareService($zone->cloudflareAccount);
        $result = $service->getDnsRecords($zone->zone_id);

        if (!$result['success']) {
            return back()->with('error', 'Failed to fetch DNS records.');
        }

        $this->syncRecordPage($zone, $result);

        $page = 2;
        while (isset($result['result_info']['total_pages']) && $page <= $result['result_info']['total_pages']) {
            $pageResult = $service->getDnsRecords($zone->zone_id, $page);
            if (!$pageResult['success']) break;
            $this->syncRecordPage($zone, $pageResult);
            $page++;
        }

        return to_route('zones.records', $zone)->with('success', 'DNS records synced.');
    }

    public function syncRecordPage(Zone $zone, array $result): void
    {
        foreach ($result['result'] as $recordData) {
            $zone->dnsRecords()->updateOrCreate(
                ['record_id' => $recordData['id']],
                [
                    'type' => $recordData['type'],
                    'name' => $recordData['name'],
                    'content' => $recordData['content'],
                    'proxied' => $recordData['proxied'] ?? false,
                    'ttl' => $recordData['ttl'] ?? 120,
                    'synced_at' => now(),
                ]
            );
        }
    }

    public function createRecord(Request $request, Zone $zone): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV',
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:500',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'proxied' => 'nullable|boolean',
        ]);

        $service = new CloudflareService($zone->cloudflareAccount);
        $result = $service->createDnsRecord($zone->zone_id, [
            'type' => $validated['type'],
            'name' => $validated['name'],
            'content' => $validated['content'],
            'ttl' => $validated['ttl'] ?? 120,
            'proxied' => $validated['proxied'] ?? false,
        ]);

        if (!$result['success']) {
            return back()->with('error', 'Failed to create record: ' . ($result['errors'][0]['message'] ?? 'Unknown error'));
        }

        $zone->dnsRecords()->create([
            'record_id' => $result['result']['id'],
            'type' => $result['result']['type'],
            'name' => $result['result']['name'],
            'content' => $result['result']['content'],
            'proxied' => $result['result']['proxied'] ?? false,
            'ttl' => $result['result']['ttl'] ?? 120,
            'synced_at' => now(),
        ]);

        return to_route('zones.records', $zone)->with('success', 'DNS record created.');
    }

    public function updateRecord(Request $request, DnsRecord $dnsRecord): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:500',
            'type' => 'required|string|in:A,AAAA,CNAME,MX,TXT,NS,SRV',
            'ttl' => 'nullable|integer|min:60|max:86400',
            'proxied' => 'nullable|boolean',
        ]);

        $dnsRecord->load('zone.cloudflareAccount');
        $service = new CloudflareService($dnsRecord->zone->cloudflareAccount);
        $oldType = $dnsRecord->type;
        $oldContent = $dnsRecord->content;
        $typeChanged = $oldType !== $validated['type'];

        if ($typeChanged) {
            $delResult = $service->deleteDnsRecord($dnsRecord->zone->zone_id, $dnsRecord->record_id);
            if (!isset($delResult['success']) || !$delResult['success']) {
                return back()->with('error', 'Gagal hapus record lama: ' . ($delResult['errors'][0]['message'] ?? 'Unknown'));
            }

            $createResult = $service->createDnsRecord($dnsRecord->zone->zone_id, [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'content' => $validated['content'],
                'ttl' => $validated['ttl'] ?? 120,
                'proxied' => $validated['proxied'] ?? false,
            ]);

            if (!isset($createResult['success']) || !$createResult['success']) {
                return back()->with('error', 'Gagal buat record baru: ' . ($createResult['errors'][0]['message'] ?? 'Unknown'));
            }

            $r = $createResult['result'];
            $dnsRecord->update([
                'record_id' => $r['id'],
                'type' => $r['type'],
                'name' => rtrim($r['name'] ?? '', '.'),
                'content' => $r['content'],
                'ttl' => $r['ttl'] ?? 120,
                'proxied' => $r['proxied'] ?? false,
                'synced_at' => now(),
            ]);
        } else {
            $result = $service->updateDnsRecord($dnsRecord->zone->zone_id, $dnsRecord->record_id, [
                'type' => $validated['type'],
                'name' => $validated['name'],
                'content' => $validated['content'],
                'ttl' => $validated['ttl'] ?? 120,
                'proxied' => $validated['proxied'] ?? false,
            ]);

            if (!$result['success']) {
                return back()->with('error', 'Gagal update: ' . ($result['errors'][0]['message'] ?? 'Unknown error'));
            }

            $dnsRecord->update([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'content' => $validated['content'],
                'ttl' => $validated['ttl'] ?? 120,
                'proxied' => $validated['proxied'] ?? false,
                'synced_at' => now(),
            ]);
        }

        $trackedDomain = TrackedDomain::where('dns_record_id', $dnsRecord->id)->first();
        if ($trackedDomain && $validated['content'] !== $oldContent) {
            $trackedDomain->update(['ip_address' => $validated['content'], 'last_synced_at' => now()]);
        }

        DnsUpdateLog::create([
            'tracked_domain_id' => $trackedDomain?->id,
            'zone_name' => $dnsRecord->zone->name,
            'record_name' => $dnsRecord->name,
            'record_type' => $dnsRecord->type,
            'old_ip' => $oldContent,
            'new_ip' => $validated['content'],
            'status' => 'success',
            'response_message' => $typeChanged ? "Converted from {$oldType} to {$validated['type']}" : 'Updated via edit',
        ]);

        return back()->with('success', "Record {$dnsRecord->name} updated.");
    }

    public function deleteRecord(DnsRecord $dnsRecord): RedirectResponse
    {
        $dnsRecord->load('zone.cloudflareAccount');
        $service = new CloudflareService($dnsRecord->zone->cloudflareAccount);

        $result = $service->deleteDnsRecord($dnsRecord->zone->zone_id, $dnsRecord->record_id);
        if (!isset($result['success']) || !$result['success']) {
            return back()->with('error', 'Gagal hapus: ' . ($result['errors'][0]['message'] ?? 'Unknown'));
        }

        TrackedDomain::where('dns_record_id', $dnsRecord->id)->delete();
        $dnsRecord->delete();

        return back()->with('success', 'DNS record deleted.');
    }

    public function updateSingle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dns_record_id' => 'required|exists:dns_records,id',
            'ip_address' => 'required|ip',
        ]);

        $record = DnsRecord::with('zone.cloudflareAccount')->findOrFail($validated['dns_record_id']);
        $service = new CloudflareService($record->zone->cloudflareAccount);
        $oldIp = $record->content;

        $result = $service->updateDnsRecord($record->zone->zone_id, $record->record_id, [
            'type' => $record->type,
            'name' => $record->name,
            'content' => $validated['ip_address'],
            'ttl' => $record->ttl,
            'proxied' => $record->proxied,
        ]);

        $status = $result['success'] ? 'success' : 'failed';
        $trackedDomain = TrackedDomain::where('dns_record_id', $record->id)->first();

        DnsUpdateLog::create([
            'tracked_domain_id' => $trackedDomain?->id,
            'zone_name' => $record->zone->name,
            'record_name' => $record->name,
            'record_type' => $record->type,
            'old_ip' => $oldIp,
            'new_ip' => $validated['ip_address'],
            'status' => $status,
            'response_message' => $result['success'] ? 'Updated' : ($result['errors'][0]['message'] ?? 'Unknown error'),
        ]);

        if ($result['success']) {
            $record->update([
                'content' => $validated['ip_address'],
                'synced_at' => now(),
            ]);
            if ($trackedDomain) {
                $trackedDomain->update([
                    'ip_address' => $validated['ip_address'],
                    'last_synced_at' => now(),
                ]);
            }
            return back()->with('success', "{$record->name} updated to {$validated['ip_address']}.");
        }

        return back()->with('error', "Failed to update {$record->name}: " . ($result['errors'][0]['message'] ?? 'Unknown error'));
    }

    public function updateBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'ip_address' => 'required|ip',
            'record_ids' => 'required|array',
            'record_ids.*' => 'exists:dns_records,id',
        ]);

        $zone = Zone::with('cloudflareAccount')->findOrFail($validated['zone_id']);
        $service = new CloudflareService($zone->cloudflareAccount);
        $records = DnsRecord::whereIn('id', $validated['record_ids'])->where('zone_id', $zone->id)->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($records as $record) {
            $oldIp = $record->content;
            $result = $service->updateDnsRecord($zone->zone_id, $record->record_id, [
                'type' => $record->type,
                'name' => $record->name,
                'content' => $validated['ip_address'],
                'ttl' => $record->ttl,
                'proxied' => $record->proxied,
            ]);

            $trackedDomain = TrackedDomain::where('dns_record_id', $record->id)->first();

            DnsUpdateLog::create([
                'tracked_domain_id' => $trackedDomain?->id,
                'zone_name' => $zone->name,
                'record_name' => $record->name,
                'record_type' => $record->type,
                'old_ip' => $oldIp,
                'new_ip' => $validated['ip_address'],
                'status' => $result['success'] ? 'success' : 'failed',
                'response_message' => $result['success'] ? 'Updated' : ($result['errors'][0]['message'] ?? 'Unknown error'),
            ]);

            if ($result['success']) {
                $record->update([
                    'content' => $validated['ip_address'],
                    'synced_at' => now(),
                ]);
                if ($trackedDomain) {
                    $trackedDomain->update([
                        'ip_address' => $validated['ip_address'],
                        'last_synced_at' => now(),
                    ]);
                }
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $message = "Bulk update completed. {$successCount} succeeded";
        if ($failCount > 0) {
            $message .= ", {$failCount} failed";
        }

        return back()->with('success', $message);
    }

    public function updateView(): View
    {
        $zones = Zone::with('cloudflareAccount', 'dnsRecords')->get();
        $accounts = CloudflareAccount::where('is_active', true)->get();
        return view('dns.update', compact('zones', 'accounts'));
    }

    public function logs(): View
    {
        $logs = DnsUpdateLog::with('trackedDomain')->latest()->paginate(50);
        return view('dns.logs', compact('logs'));
    }
}
