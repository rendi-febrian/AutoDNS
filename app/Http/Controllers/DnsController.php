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

        return to_route('zones.records', $zone)->with('success', 'DNS records synced.');
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
