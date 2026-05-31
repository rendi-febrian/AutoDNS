<?php

namespace App\Http\Controllers;

use App\Models\CloudflareAccount;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use App\Models\Zone;
use App\Services\CloudflareService;
use Illuminate\Http\JsonResponse;
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

        $serverStats = $this->getServerStats();

        return view('dashboard', compact(
            'serverIp',
            'totalZones',
            'totalAccounts',
            'totalTracked',
            'activeTracked',
            'recentLogs',
            'zones',
            'serverStats'
        ));
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->getServerStats());
    }

    private function getServerStats(): array
    {
        // CPU
        $cpu = $this->getCpuUsage();

        // RAM
        $memInfo = $this->parseProcMeminfo();
        $ramTotal = $memInfo['MemTotal'] ?? 0;
        $ramAvail = $memInfo['MemAvailable'] ?? 0;
        $ramUsed = $ramTotal - $ramAvail;

        // Disk
        $diskTotal = @disk_total_space('/') ?: 0;
        $diskFree = @disk_free_space('/') ?: 0;
        $diskUsed = $diskTotal - $diskFree;

        // Uptime
        $uptime = $this->getUptime();

        // Load average
        $load = sys_getloadavg();

        return [
            'cpu' => round($cpu, 1),
            'ram_total' => $ramTotal,
            'ram_used' => $ramUsed,
            'ram_percent' => $ramTotal > 0 ? round($ramUsed / $ramTotal * 100, 1) : 0,
            'disk_total' => $diskTotal,
            'disk_used' => $diskUsed,
            'disk_percent' => $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : 0,
            'uptime' => $uptime,
            'load' => $load,
        ];
    }

    private function getCpuUsage(): float
    {
        $prev = $this->readCpuStat();
        if (!$prev) return 0;
        usleep(200000); // 200ms
        $curr = $this->readCpuStat();
        if (!$curr) return 0;

        $prevIdle = $prev['idle'] + $prev['iowait'];
        $currIdle = $curr['idle'] + $curr['iowait'];
        $prevTotal = array_sum($prev);
        $currTotal = array_sum($curr);
        $totalDiff = $currTotal - $prevTotal;
        $idleDiff = $currIdle - $prevIdle;

        return $totalDiff > 0 ? ($totalDiff - $idleDiff) / $totalDiff * 100 : 0;
    }

    private function readCpuStat(): ?array
    {
        $stat = @file_get_contents('/proc/stat');
        if (!$stat) return null;
        if (!preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $stat, $m)) return null;
        return [
            'user' => (int)$m[1],
            'nice' => (int)$m[2],
            'system' => (int)$m[3],
            'idle' => (int)$m[4],
            'iowait' => (int)$m[5],
            'irq' => (int)$m[6],
            'softirq' => (int)$m[7],
            'steal' => (int)$m[8],
        ];
    }

    private function parseProcMeminfo(): array
    {
        $data = @file_get_contents('/proc/meminfo');
        if (!$data) return [];
        $result = [];
        foreach (explode("\n", $data) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+kB$/', $line, $m)) {
                $result[$m[1]] = (int)$m[2] * 1024;
            }
        }
        return $result;
    }

    private function getUptime(): string
    {
        $uptime = @file_get_contents('/proc/uptime');
        if (!$uptime) return 'N/A';
        $seconds = (int)explode(' ', $uptime)[0];
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        $parts[] = "{$minutes}m";
        return implode(' ', $parts);
    }
}
