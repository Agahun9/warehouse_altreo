<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

class JwtService
{
    public function issue(array $payload, $ttl = null): string
    {
        $config = Config::get('app');
        $issuedAt = time();
        $expiresAt = $issuedAt + ($ttl !== null ? (int) $ttl : (int) $config['jwt_ttl']);

        $tokenPayload = array_merge($payload, array(
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ));

        $header = $this->base64UrlEncode(json_encode(array('alg' => 'HS256', 'typ' => 'JWT')));
        $body = $this->base64UrlEncode(json_encode($tokenPayload));
        $signature = $this->sign($header . '.' . $body, (string) $config['jwt_secret']);

        return $header . '.' . $body . '.' . $signature;
    }

    public function validate($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $config = Config::get('app');
        $expectedSignature = $this->sign($parts[0] . '.' . $parts[1], (string) $config['jwt_secret']);

        if (!hash_equals($expectedSignature, $parts[2])) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!is_array($payload)) {
            return null;
        }

        if (!isset($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function sign($data, $secret): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
    }

    private function base64UrlEncode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode($data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
