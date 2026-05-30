<?php

namespace App\Http\Controllers;

use App\Models\CloudflareAccount;
use App\Services\CloudflareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CloudflareAccountController extends Controller
{
    public function index(): View
    {
        $accounts = CloudflareAccount::withCount('zones')->latest()->get();
        return view('cloudflare.accounts', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_token' => 'required|string',
        ]);

        $service = (new CloudflareService)->setApiToken($validated['api_token']);

        $verify = $service->verifyToken();
        if (!$verify['success']) {
            return back()->withErrors(['api_token' => 'Token tidak valid: ' . ($verify['errors'][0]['message'] ?? 'Unknown error')])->withInput();
        }

        $tokenName = $verify['result']['name'] ?? 'Cloudflare';

        $email = null;
        $userResult = $service->getUser();
        if ($userResult['success']) {
            $email = $userResult['result']['email'] ?? null;
            $tokenName = $userResult['result']['username'] ?? $userResult['result']['email'] ?? $tokenName;
        }

        CloudflareAccount::create([
            'name' => $tokenName,
            'api_token' => $validated['api_token'],
            'account_email' => $email,
            'is_active' => true,
        ]);

        $msg = $email
            ? "✅ Terhubung sebagai {$email}"
            : "✅ Terhubung! Token: {$tokenName}";

        return to_route('cloudflare.accounts.index')
            ->with('success', $msg . ' Buka akun untuk lihat zones.');
    }

    public function destroy(CloudflareAccount $cloudflareAccount): RedirectResponse
    {
        $cloudflareAccount->delete();
        return to_route('cloudflare.accounts.index')->with('success', 'Account removed.');
    }

    public function zones(CloudflareAccount $cloudflareAccount): View
    {
        $service = new CloudflareService($cloudflareAccount);
        $result = $service->getZones();

        $zones = [];
        if ($result['success']) {
            $zones = $result['result'];
        }

        return view('cloudflare.zones', compact('cloudflareAccount', 'zones'));
    }

    public function syncZoneRecords(CloudflareAccount $cloudflareAccount, string $zoneId): RedirectResponse
    {
        $service = new CloudflareService($cloudflareAccount);

        $zoneResult = $service->getZones();
        $zoneName = null;
        $zoneStatus = null;
        if ($zoneResult['success']) {
            foreach ($zoneResult['result'] as $z) {
                if ($z['id'] === $zoneId) {
                    $zoneName = $z['name'];
                    $zoneStatus = $z['status'];
                    break;
                }
            }
        }

        if (!$zoneName) {
            return back()->with('error', 'Zone not found.');
        }

        $zone = $cloudflareAccount->zones()->updateOrCreate(
            ['zone_id' => $zoneId],
            [
                'name' => $zoneName,
                'status' => $zoneStatus ?? 'unknown',
                'synced_at' => now(),
            ]
        );

        $recordsResult = $service->getDnsRecords($zoneId);
        if (!isset($recordsResult['success']) || !$recordsResult['success']) {
            return to_route('zones.records', $zone)->with('warning', 'Zone tersimpan tapi gagal sync records: ' . ($recordsResult['errors'][0]['message'] ?? 'Unknown error'));
        }

        app(DnsController::class)->syncRecordPage($zone, $recordsResult);

        $page = 2;
        while (isset($recordsResult['result_info']['total_pages']) && $page <= $recordsResult['result_info']['total_pages']) {
            $pageResult = $service->getDnsRecords($zoneId, $page);
            if (!$pageResult['success']) break;
            app(DnsController::class)->syncRecordPage($zone, $pageResult);
            $page++;
        }

        return to_route('zones.records', $zone)
            ->with('success', "Zone {$zoneName} siap. Pilih record yg mau di-track ke auto-sync.");
    }
}
