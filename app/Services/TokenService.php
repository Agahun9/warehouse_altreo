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
        $config = Config::get('app');
        $base = (string) $config['base_url'];

        return $base . '?controller=auth&action=' . urlencode((string) $action) . '&token=' . urlencode((string) $token);
    }
}
