<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

class TokenService
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function buildUrl($action, $token): string
    {
        $base = $this->baseUrl();

        return $base . '?controller=auth&action=' . urlencode((string) $action) . '&token=' . urlencode((string) $token);
    }

    public function baseUrl(): string
    {
        $config = Config::get('app');
        $publicBase = trim((string) ($config['public_base_url'] ?? ''));
        if ($publicBase !== '') {
            return rtrim($publicBase, '?&');
        }

        $base = trim((string) ($config['base_url'] ?? './index.php'));
        if ($base !== '' && preg_match('#^https?://#i', $base) === 1) {
            return rtrim($base, '?&');
        }

        return $base !== '' ? $base : './index.php';
    }
}
