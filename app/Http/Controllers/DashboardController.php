<?php

namespace App\Http\Controllers;

use App\Models\CloudflareAccount;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use App\Models\Zone;
use App\Services\CloudflareService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $service = new CloudflareService;
        $serverIp = $service->getPublicIp() ?? 'N/A';

        $totalZones = Zone::count();
        $totalAccounts = CloudflareAccount::count();
        $totalTracked = TrackedDomain::count();
        $activeTracked = TrackedDomain::where('is_active', true)->count();
        $recentLogs = DnsUpdateLog::with('trackedDomain')->latest()->take(10)->get();

        $zones = Zone::with('cloudflareAccount', 'dnsRecords')->get();

        return view('dashboard', compact(
            'serverIp',
            'totalZones',
            'totalAccounts',
            'totalTracked',
            'activeTracked',
            'recentLogs',
            'zones'
        ));
    }
}
