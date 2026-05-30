<?php

namespace Tests\Feature;

use App\Models\CloudflareAccount;
use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use App\Models\User;
use App\Models\Zone;
use App\Services\ConfigParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoDnsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Guest Access ──

    public function test_guest_is_redirected_to_login()
    {
        $routes = ['/dashboard', '/cloudflare/accounts', '/tracked-domains', '/dns/update', '/logs'];
        foreach ($routes as $route) {
            $this->get($route)->assertRedirect('/login');
        }
    }

    // ── Dashboard ──

    public function test_dashboard_loads_with_stats()
    {
        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Server IP')
            ->assertSee('Zones')
            ->assertSee('Tracked Domains')
            ->assertSee('Cloudflare Accounts');
    }

    public function test_dashboard_shows_zones_when_available()
    {
        $account = CloudflareAccount::factory()->create();
        $zone = Zone::factory()->create(['cloudflare_account_id' => $account->id]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($zone->name);
    }

    public function test_dashboard_shows_recent_logs()
    {
        $log = DnsUpdateLog::factory()->create();

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($log->record_name)
            ->assertSee($log->new_ip);
    }

    // ── Cloudflare Accounts ──

    public function test_cloudflare_accounts_page_loads()
    {
        $this->actingAs($this->user)
            ->get('/cloudflare/accounts')
            ->assertOk()
            ->assertSee('Cloudflare')
            ->assertSee('API Token')
            ->assertSee('Verifikasi');
    }

    public function test_cloudflare_accounts_shows_existing_accounts()
    {
        $account = CloudflareAccount::factory()->create();

        $this->actingAs($this->user)
            ->get('/cloudflare/accounts')
            ->assertOk()
            ->assertSee($account->name)
            ->assertSee('Browse Zones');
    }

    public function test_cloudflare_account_store_rejects_empty_token()
    {
        $this->actingAs($this->user)
            ->post('/cloudflare/accounts', ['api_token' => ''])
            ->assertSessionHasErrors('api_token');
    }

    public function test_cloudflare_account_store_rejects_invalid_token()
    {
        $this->actingAs($this->user)
            ->post('/cloudflare/accounts', ['api_token' => 'invalid-token'])
            ->assertSessionHasErrors('api_token');
    }

    // ── Zones & DNS Records ──

    public function test_zone_records_page_loads()
    {
        $account = CloudflareAccount::factory()->create();
        $zone = Zone::factory()->create(['cloudflare_account_id' => $account->id]);

        $this->actingAs($this->user)
            ->get("/zones/{$zone->id}/records")
            ->assertOk()
            ->assertSee($zone->name)
            ->assertSee('DNS Records')
            ->assertSee('New DNS Record');
    }

    // ── Tracked Domains ──

    public function test_tracked_domains_page_loads()
    {
        $this->actingAs($this->user)
            ->get('/tracked-domains')
            ->assertOk()
            ->assertSee('Tracked Domains')
            ->assertSee('Add Domain')
            ->assertSee('Import from Config')
            ->assertSee('Sync All to Server IP');
    }

    public function test_tracked_domains_store_rejects_duplicate()
    {
        TrackedDomain::factory()->create(['domain_name' => 'example.com']);

        $this->actingAs($this->user)
            ->post('/tracked-domains', [
                'domain_name' => 'example.com',
                'zone_name' => 'example.com',
            ])
            ->assertSessionHasErrors('domain_name');
    }

    public function test_tracked_domains_store_rejects_empty()
    {
        $this->actingAs($this->user)
            ->post('/tracked-domains', ['domain_name' => '', 'zone_name' => ''])
            ->assertSessionHasErrors(['domain_name', 'zone_name']);
    }

    public function test_tracked_domains_store_creates_tracked_domain()
    {
        $this->actingAs($this->user)
            ->post('/tracked-domains', [
                'domain_name' => 'test.example.com',
                'zone_name' => 'example.com',
            ])
            ->assertRedirect('/tracked-domains');

        $this->assertDatabaseHas('tracked_domains', [
            'domain_name' => 'test.example.com',
            'zone_name' => 'example.com',
        ]);
    }

    public function test_tracked_domains_shows_existing_domains()
    {
        $domain = TrackedDomain::factory()->create();

        $this->actingAs($this->user)
            ->get('/tracked-domains')
            ->assertOk()
            ->assertSee($domain->domain_name)
            ->assertSee($domain->zone_name);
    }

    public function test_tracked_domain_can_be_deleted()
    {
        $domain = TrackedDomain::factory()->create();

        $this->actingAs($this->user)
            ->delete("/tracked-domains/{$domain->id}")
            ->assertRedirect('/tracked-domains');

        $this->assertDatabaseMissing('tracked_domains', ['id' => $domain->id]);
    }

    // ── DNS Update Page ──

    public function test_dns_update_page_loads()
    {
        $this->actingAs($this->user)
            ->get('/dns/update')
            ->assertOk()
            ->assertSee('DNS Update')
            ->assertSee('Single Update')
            ->assertSee('Bulk Update');
    }

    // ── Logs ──

    public function test_logs_page_loads()
    {
        $this->actingAs($this->user)
            ->get('/logs')
            ->assertOk()
            ->assertSee('Activity Logs');
    }

    public function test_logs_shows_recent_activity()
    {
        $log = DnsUpdateLog::factory()->create();

        $this->actingAs($this->user)
            ->get('/logs')
            ->assertOk()
            ->assertSee($log->record_name)
            ->assertSee($log->record_type)
            ->assertSee($log->new_ip);
    }

    // ── ConfigParserService ──

    public function test_config_parser_parses_nginx_server_names()
    {
        $parser = new ConfigParserService;
        $content = <<<NGINX
server {
    listen 80;
    server_name example.com www.example.com;
}
server {
    listen 443 ssl;
    server_name api.example.com;
}
NGINX;

        $domains = $parser->parseNginxConfig($content);
        $this->assertCount(3, $domains);
        $this->assertContains('example.com', $domains);
        $this->assertContains('www.example.com', $domains);
        $this->assertContains('api.example.com', $domains);
    }

    public function test_config_parser_parses_apache_server_names()
    {
        $parser = new ConfigParserService;
        $content = <<<APACHE
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com
</VirtualHost>
APACHE;

        $domains = $parser->parseApacheConfig($content);
        $this->assertCount(2, $domains);
        $this->assertContains('example.com', $domains);
        $this->assertContains('www.example.com', $domains);
    }

    public function test_config_parser_detects_zone_from_domain()
    {
        $parser = new ConfigParserService;

        $this->assertEquals('example.com', $parser->detectZone('sub.example.com'));
        $this->assertEquals('example.co.id', $parser->detectZone('sub.example.co.id'));
        $this->assertEquals('example.co.uk', $parser->detectZone('sub.example.co.uk'));
        $this->assertEquals('domain.go.id', $parser->detectZone('sub.domain.go.id'));
    }

    public function test_config_parser_handles_underscore_server_name()
    {
        $parser = new ConfigParserService;
        $content = 'server_name _;';
        $domains = $parser->parseNginxConfig($content);
        $this->assertEmpty($domains);
    }

    // ── Import Config Validation ──

    public function test_import_config_requires_content_for_manual()
    {
        $this->actingAs($this->user)
            ->post('/tracked-domains/import-config', [
                'config_type' => 'manual',
                'config_content' => '',
            ])
            ->assertSessionHasErrors('config_content');
    }

    // ── Profile ──

    public function test_profile_page_loads()
    {
        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Profile Information');
    }
}
