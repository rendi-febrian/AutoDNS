<?php

namespace App\Services;

class ConfigParserService
{
    public function parseNginxConfig(string $content): array
    {
        $domains = [];
        preg_match_all('/server_name\s+(.+?);/s', $content, $matches);
        foreach ($matches[1] as $match) {
            $names = preg_split('/\s+/', trim($match));
            foreach ($names as $name) {
                $name = trim($name);
                if (!empty($name) && $name !== '_') {
                    $domains[] = $name;
                }
            }
        }
        return array_unique($domains);
    }

    public function parseApacheConfig(string $content): array
    {
        $domains = [];
        preg_match_all('/ServerName\s+(\S+)/i', $content, $matches);
        foreach ($matches[1] as $name) {
            $domains[] = trim($name);
        }
        preg_match_all('/ServerAlias\s+(.+)/i', $content, $matches);
        foreach ($matches[1] as $match) {
            $names = preg_split('/\s+/', trim($match));
            foreach ($names as $name) {
                $name = trim($name);
                if (!empty($name)) {
                    $domains[] = $name;
                }
            }
        }
        return array_unique($domains);
    }

    public function detectZone(string $domain): string
    {
        $parts = explode('.', $domain);
        $count = count($parts);
        if ($count < 2) return $domain;
        $tld = $parts[$count - 1];
        $sld = $parts[$count - 2];
        $zone = $sld . '.' . $tld;
        if (in_array($tld, ['id', 'uk', 'jp', 'au']) && $count >= 3) {
            $thirdLevel = $parts[$count - 3];
            $coDomains = ['co', 'or', 'ac', 'go', 'web', 'sch', 'net'];
            if (!in_array($thirdLevel, $coDomains)) {
                return $thirdLevel . '.' . $zone;
            }
        }
        return $zone;
    }

    public function readFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }
        return file_get_contents($filePath);
    }

    public function getNginxSitesEnabled(string $dir = '/etc/nginx/sites-enabled'): array
    {
        if (!is_dir($dir)) return [];
        $files = glob("$dir/*");
        $domains = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $content = $this->readFile($file);
                if ($content) {
                    $domains = array_merge($domains, $this->parseNginxConfig($content));
                }
            }
        }
        return array_unique($domains);
    }

    public function getApacheSitesEnabled(string $dir = '/etc/apache2/sites-enabled'): array
    {
        if (!is_dir($dir)) return [];
        $files = glob("$dir/*");
        $domains = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $content = $this->readFile($file);
                if ($content) {
                    $domains = array_merge($domains, $this->parseApacheConfig($content));
                }
            }
        }
        return array_unique($domains);
    }
}
