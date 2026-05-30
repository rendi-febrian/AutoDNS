<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class FixCore extends Command
{
    protected $signature = 'fix:core';
    protected $description = 'Check and fix common Auto DNS Domain issues';

    public function handle(): int
    {
        $fixed = 0;
        $skipped = 0;

        $this->info('Auto DNS Domain — Fix Core');
        $this->newLine();

        // ── 1. .env ──
        $this->line('── .env ──');
        if (!file_exists(base_path('.env'))) {
            if (file_exists(base_path('.env.example'))) {
                File::copy(base_path('.env.example'), base_path('.env'));
                $this->info('  ✓ .env created from .env.example');
                $fixed++;
            }
        } else {
            $this->line('  ✓ .env exists');
            $skipped++;
        }

        // ── 2. APP_KEY ──
        $this->line('── APP_KEY ──');
        if (empty(config('app.key'))) {
            $this->call('key:generate', ['--force' => true]);
            $this->info('  ✓ APP_KEY generated');
            $fixed++;
        } else {
            $this->line('  ✓ APP_KEY is set');
            $skipped++;
        }

        // ── 3. Database ──
        $this->line('── Database ──');
        $dbFile = database_path('database.sqlite');
        if (!file_exists($dbFile)) {
            $sample = database_path('sample.sqlite');
            if (file_exists($sample)) {
                File::copy($sample, $dbFile);
                $this->info('  ✓ database.sqlite created from sample');
                $fixed++;
            } else {
                File::put($dbFile, '');
                $this->info('  ✓ database.sqlite created (empty)');
                $fixed++;
            }
        } else {
            $this->line('  ✓ database.sqlite exists');
            $skipped++;
        }

        // ── 4. Migrations ──
        $this->line('── Migrations ──');
        $this->call('migrate', ['--force' => true]);
        $this->info('  ✓ Migrations ran');

        // ── 5. Storage permissions ──
        $this->line('── Permissions ──');
        $dirs = ['storage', 'bootstrap/cache', 'database'];
        $webUser = $this->detectWebUser();
        foreach ($dirs as $dir) {
            $path = base_path($dir);
            if (!is_writable($path)) {
                if ($webUser) {
                    $result = Process::run("chown -R {$webUser}:{$webUser} {$path}");
                    if ($result->successful()) {
                        $this->info("  ✓ {$dir} permissions fixed (chown {$webUser})");
                        $fixed++;
                    } else {
                        $this->warn("  ⚠ {$dir} not writable — need sudo: chown -R {$webUser}:{$webUser} {$path}");
                    }
                } else {
                    $this->warn("  ⚠ {$dir} not writable — chmod -R 775 {$path}");
                }
            } else {
                $this->line("  ✓ {$dir} is writable");
                $skipped++;
            }
        }

        // ── 6. Cek APP_URL di .env ──
        $this->line('── APP_URL ──');
        $envContent = file_get_contents(base_path('.env'));
        if (preg_match('/^APP_URL=http:\/\/localhost(:\d+)?$/m', $envContent)) {
            $this->warn('  ⚠ APP_URL masih localhost — ganti dengan domain asli di .env');
        } else {
            $this->line('  ✓ APP_URL sudah dikonfigurasi');
            $skipped++;
        }

        $this->newLine();
        $this->info("Done: {$fixed} fixed, {$skipped} already ok.");

        return self::SUCCESS;
    }

    private function detectWebUser(): ?string
    {
        if (file_exists('/etc/apache2/envvars')) {
            $content = file_get_contents('/etc/apache2/envvars');
            if (preg_match('/^export APACHE_RUN_USER=(.+)$/m', $content, $m)) {
                return trim($m[1], '"');
            }
        }
        $candidates = ['www-data', 'nginx', 'nobody'];
        foreach ($candidates as $user) {
            if (file_exists("/run/user/{$user}") || is_dir("/var/cache/{$user}")) {
                return $user;
            }
        }
        return null;
    }
}
