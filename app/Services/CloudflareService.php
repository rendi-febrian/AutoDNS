<?php

namespace App\Services;

use App\Models\CloudflareAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CloudflareService
{
    private string $apiToken;
    private string $baseUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct(?CloudflareAccount $account = null)
    {
        if ($account) {
            $this->apiToken = $account->api_token;
        }
    }

    public function setApiToken(string $token): self
    {
        $this->apiToken = $token;
        return $this;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30);
    }

    public function verifyToken(): array
    {
        try {
            $resp = $this->request()->get("{$this->baseUrl}/user/tokens/verify");
            return $resp->json();
        } catch (ConnectionException $e) {
            return ['success' => false, 'errors' => [['message' => 'Connection timeout']]];
        }
    }

    public function getUser(): array
    {
        try {
            $resp = $this->request()->get("{$this->baseUrl}/user");
            return $resp->json();
        } catch (ConnectionException $e) {
            return ['success' => false, 'errors' => [['message' => 'Connection timeout']]];
        }
    }

    public function getZones(): array
    {
        $resp = $this->request()->get("{$this->baseUrl}/zones", [
            'per_page' => 100,
        ]);
        return $resp->json();
    }

    public function getDnsRecords(string $zoneId): array
    {
        $resp = $this->request()->get("{$this->baseUrl}/zones/{$zoneId}/dns_records", [
            'per_page' => 100,
        ]);
        return $resp->json();
    }

    public function updateDnsRecord(string $zoneId, string $recordId, array $data): array
    {
        $resp = $this->request()->put(
            "{$this->baseUrl}/zones/{$zoneId}/dns_records/{$recordId}",
            $data
        );
        return $resp->json();
    }

    public function createDnsRecord(string $zoneId, array $data): array
    {
        $resp = $this->request()->post(
            "{$this->baseUrl}/zones/{$zoneId}/dns_records",
            $data
        );
        return $resp->json();
    }

    public function getDnsRecord(string $zoneId, string $recordId): array
    {
        $resp = $this->request()->get("{$this->baseUrl}/zones/{$zoneId}/dns_records/{$recordId}");
        return $resp->json();
    }

    public function getPublicIp(): ?string
    {
        try {
            $resp = Http::timeout(10)->get('https://ipv4.icanhazip.com');
            if ($resp->successful()) {
                return trim($resp->body());
            }
        } catch (ConnectionException $e) {
            return null;
        }
        return null;
    }
}
